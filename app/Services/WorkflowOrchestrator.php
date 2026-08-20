<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Events\WorkflowUpdated;
use App\Jobs\ExecuteTask;
use App\Jobs\PostProcessWorkflow;
use App\Models\Workflow;
use App\Models\WorkflowTask;
use Illuminate\Support\Facades\DB;

class WorkflowOrchestrator
{
    /**
     * Advance the workflow: cascade-fail unrunnable tasks, dispatch any tasks
     * whose dependencies are now satisfied, and move to post-processing when
     * every task has reached a terminal state.
     */
    public function dispatchReadyTasks(Workflow $workflow): void
    {
        $changed = false;

        /** @var array<int, int> $claimedIds */
        $claimedIds = DB::transaction(function () use ($workflow, &$changed): array {
            $tasks = $workflow->tasks()->lockForUpdate()->get();

            $completedKeys = $tasks->where('status', TaskStatus::Completed)->pluck('key')->all();
            $failedKeys = $tasks->where('status', TaskStatus::Failed)->pluck('key')->all();

            $claimed = [];

            foreach ($tasks->where('status', TaskStatus::Pending) as $task) {
                $dependsOn = $task->depends_on ?? [];

                // A prerequisite failed — this task can never run, so fail it too.
                if (array_intersect($dependsOn, $failedKeys) !== []) {
                    if ($this->claim($task, TaskStatus::Failed, [
                        'output' => 'Skipped: a prerequisite task failed.',
                        'completed_at' => now(),
                    ])) {
                        $changed = true;
                    }

                    continue;
                }

                // All dependencies satisfied — claim and queue the task.
                if (array_diff($dependsOn, $completedKeys) === []) {
                    if ($this->claim($task, TaskStatus::Queued)) {
                        $claimed[] = $task->id;
                        $changed = true;
                    }
                }
            }

            return $claimed;
        });

        foreach ($claimedIds as $taskId) {
            ExecuteTask::dispatch($taskId);
        }

        if ($changed) {
            WorkflowUpdated::dispatch($workflow->refresh());
        }

        $this->maybePostProcess($workflow);
    }

    /**
     * Handle a task reaching a terminal state by advancing the workflow.
     */
    public function handleTaskCompleted(WorkflowTask $task): void
    {
        $this->dispatchReadyTasks($task->workflow);
    }

    /**
     * Atomically claim a pending task, transitioning it to the given status.
     * Returns true only if this call won the claim.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function claim(WorkflowTask $task, TaskStatus $status, array $attributes = []): bool
    {
        return WorkflowTask::query()
            ->whereKey($task->id)
            ->where('status', TaskStatus::Pending->value)
            ->update(['status' => $status->value] + $attributes) === 1;
    }

    /**
     * Dispatch post-processing once every task is terminal, guarding against
     * concurrent task completions dispatching it more than once.
     */
    protected function maybePostProcess(Workflow $workflow): void
    {
        $hasUnfinished = $workflow->tasks()
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Failed->value])
            ->exists();

        if ($hasUnfinished) {
            return;
        }

        $won = Workflow::query()
            ->whereKey($workflow->id)
            ->where('status', WorkflowStatus::ExecutingTasks->value)
            ->update(['status' => WorkflowStatus::PostProcessing->value]) === 1;

        if ($won) {
            PostProcessWorkflow::dispatch($workflow->id);
        }
    }
}
