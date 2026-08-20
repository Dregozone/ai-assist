<?php

use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Jobs\ExecuteTask;
use App\Jobs\PostProcessWorkflow;
use App\Models\Workflow;
use App\Models\WorkflowTask;
use App\Services\WorkflowOrchestrator;
use Illuminate\Support\Facades\Bus;

/**
 * @param  array<int, string>  $dependsOn
 */
function makeTask(Workflow $workflow, string $key, TaskStatus $status, array $dependsOn = [], ?string $output = null): WorkflowTask
{
    return $workflow->tasks()->create([
        'key' => $key,
        'title' => ucfirst($key),
        'description' => "Do {$key}",
        'depends_on' => $dependsOn,
        'status' => $status,
        'output' => $output,
        'started_at' => $status === TaskStatus::Completed ? now() : null,
        'completed_at' => $status->isTerminal() ? now() : null,
    ]);
}

it('dispatches only tasks whose dependencies are all complete', function () {
    Bus::fake();

    $workflow = Workflow::factory()->create(['status' => WorkflowStatus::ExecutingTasks]);
    $t1 = makeTask($workflow, 't1', TaskStatus::Pending);
    $t2 = makeTask($workflow, 't2', TaskStatus::Pending, ['t1']);
    $t3 = makeTask($workflow, 't3', TaskStatus::Pending);

    app(WorkflowOrchestrator::class)->dispatchReadyTasks($workflow);

    Bus::assertDispatched(ExecuteTask::class, fn (ExecuteTask $job) => $job->taskId === $t1->id);
    Bus::assertDispatched(ExecuteTask::class, fn (ExecuteTask $job) => $job->taskId === $t3->id);
    Bus::assertNotDispatched(ExecuteTask::class, fn (ExecuteTask $job) => $job->taskId === $t2->id);

    expect($t1->refresh()->status)->toBe(TaskStatus::Queued)
        ->and($t3->refresh()->status)->toBe(TaskStatus::Queued)
        ->and($t2->refresh()->status)->toBe(TaskStatus::Pending);
});

it('unblocks a dependent task once its prerequisite completes', function () {
    Bus::fake();

    $workflow = Workflow::factory()->create(['status' => WorkflowStatus::ExecutingTasks]);
    makeTask($workflow, 't1', TaskStatus::Completed, output: 'done');
    $t2 = makeTask($workflow, 't2', TaskStatus::Pending, ['t1']);

    app(WorkflowOrchestrator::class)->dispatchReadyTasks($workflow);

    Bus::assertDispatched(ExecuteTask::class, fn (ExecuteTask $job) => $job->taskId === $t2->id);
});

it('dispatches post-processing once every task is terminal', function () {
    Bus::fake();

    $workflow = Workflow::factory()->create(['status' => WorkflowStatus::ExecutingTasks]);
    makeTask($workflow, 't1', TaskStatus::Completed, output: 'done');

    app(WorkflowOrchestrator::class)->dispatchReadyTasks($workflow);

    Bus::assertDispatched(PostProcessWorkflow::class, fn (PostProcessWorkflow $job) => $job->workflowId === $workflow->id);
    expect($workflow->refresh()->status)->toBe(WorkflowStatus::PostProcessing);
});

it('cascades failure to dependents of a failed task and still finishes', function () {
    Bus::fake();

    $workflow = Workflow::factory()->create(['status' => WorkflowStatus::ExecutingTasks]);
    makeTask($workflow, 't1', TaskStatus::Failed, output: 'boom');
    $t2 = makeTask($workflow, 't2', TaskStatus::Pending, ['t1']);

    app(WorkflowOrchestrator::class)->dispatchReadyTasks($workflow);

    expect($t2->refresh()->status)->toBe(TaskStatus::Failed);
    Bus::assertNotDispatched(ExecuteTask::class);
    Bus::assertDispatched(PostProcessWorkflow::class);
});
