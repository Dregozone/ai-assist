<?php

namespace App\Jobs;

use App\Ai\Agents\PostProcessorAgent;
use App\Enums\WorkflowStatus;
use App\Events\WorkflowUpdated;
use App\Models\Workflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

class PostProcessWorkflow implements ShouldQueue
{
    use Queueable;

    public int $timeout = 320;

    public int $tries = 1;

    public function __construct(public int $workflowId) {}

    /**
     * Verify the task outputs satisfy the original request and finalise the workflow.
     */
    public function handle(): void
    {
        $workflow = Workflow::with('tasks')->findOrFail($this->workflowId);

        $workflow->update(['status' => WorkflowStatus::PostProcessing]);
        WorkflowUpdated::dispatch($workflow);

        $response = (new PostProcessorAgent)->prompt($this->buildPrompt($workflow));

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The post-processor did not return structured output.');
        }

        $success = (bool) $response['success'];

        $workflow->update([
            'post_success' => $success,
            'post_summary' => $response['summary'],
            'status' => $success ? WorkflowStatus::Succeeded : WorkflowStatus::Failed,
        ]);
        WorkflowUpdated::dispatch($workflow);
    }

    /**
     * Compose the QA prompt from the original request and every task's output.
     */
    protected function buildPrompt(Workflow $workflow): string
    {
        $prompt = "ORIGINAL REQUEST:\n{$workflow->input_text}\n\nTASK OUTPUTS:\n";

        foreach ($workflow->tasks as $task) {
            $prompt .= "\n[{$task->title}] ({$task->status->value}):\n{$task->output}\n";
        }

        return $prompt;
    }

    /**
     * Mark the workflow as failed when the job fails.
     */
    public function failed(Throwable $exception): void
    {
        Workflow::whereKey($this->workflowId)->update([
            'status' => WorkflowStatus::Failed,
            'error' => $exception->getMessage(),
        ]);

        if ($workflow = Workflow::find($this->workflowId)) {
            WorkflowUpdated::dispatch($workflow);
        }
    }
}
