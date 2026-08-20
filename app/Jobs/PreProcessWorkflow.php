<?php

namespace App\Jobs;

use App\Ai\Agents\PreProcessorAgent;
use App\Enums\WorkflowStatus;
use App\Events\WorkflowUpdated;
use App\Models\Workflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

class PreProcessWorkflow implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job may run before timing out.
     */
    public int $timeout = 320;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    public function __construct(public int $workflowId) {}

    /**
     * Optimise the raw user input into an AI-ready prompt, then hand off to processing.
     */
    public function handle(): void
    {
        $workflow = Workflow::findOrFail($this->workflowId);

        $workflow->update(['status' => WorkflowStatus::PreProcessing]);
        WorkflowUpdated::dispatch($workflow);

        $response = (new PreProcessorAgent)->prompt($workflow->input_text);

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The pre-processor did not return structured output.');
        }

        $workflow->update(['optimized_prompt' => $response['optimized_prompt']]);
        WorkflowUpdated::dispatch($workflow);

        ProcessWorkflow::dispatch($workflow->id);
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
