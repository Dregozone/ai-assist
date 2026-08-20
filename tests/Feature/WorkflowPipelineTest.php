<?php

use App\Ai\Agents\PostProcessorAgent;
use App\Ai\Agents\PreProcessorAgent;
use App\Ai\Agents\ProcessorAgent;
use App\Ai\Agents\TaskExecutorAgent;
use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Events\WorkflowUpdated;
use App\Jobs\PreProcessWorkflow;
use App\Models\Workflow;
use Illuminate\Support\Facades\Event;

it('runs the full pipeline through to a successful outcome', function () {
    Event::fake([WorkflowUpdated::class]);

    PreProcessorAgent::fake([['optimized_prompt' => 'Do the thing precisely.']]);
    ProcessorAgent::fake([['tasks' => [
        ['key' => 't1', 'title' => 'First', 'description' => 'Do first', 'depends_on' => []],
        ['key' => 't2', 'title' => 'Second', 'description' => 'Do second', 'depends_on' => ['t1']],
    ]]]);
    TaskExecutorAgent::fake(['output one', 'output two']);
    PostProcessorAgent::fake([['success' => true, 'summary' => 'All requirements met.']]);

    $workflow = Workflow::create([
        'input_text' => 'please do things',
        'status' => WorkflowStatus::Pending,
    ]);

    // Queue connection is sync in tests, so the whole pipeline runs inline.
    PreProcessWorkflow::dispatch($workflow->id);

    $workflow->refresh()->load('tasks');

    expect($workflow->status)->toBe(WorkflowStatus::Succeeded)
        ->and($workflow->optimized_prompt)->toBe('Do the thing precisely.')
        ->and($workflow->post_success)->toBeTrue()
        ->and($workflow->post_summary)->toBe('All requirements met.')
        ->and($workflow->tasks)->toHaveCount(2)
        ->and($workflow->tasks->firstWhere('key', 't1')->output)->toBe('output one')
        ->and($workflow->tasks->firstWhere('key', 't2')->output)->toBe('output two')
        ->and($workflow->tasks->every(fn ($task) => $task->status === TaskStatus::Completed))->toBeTrue();

    Event::assertDispatched(WorkflowUpdated::class);
});

it('reports failure when the post-processor is not satisfied', function () {
    Event::fake([WorkflowUpdated::class]);

    PreProcessorAgent::fake([['optimized_prompt' => 'Optimised.']]);
    ProcessorAgent::fake([['tasks' => [
        ['key' => 't1', 'title' => 'Only', 'description' => 'Do it', 'depends_on' => []],
    ]]]);
    TaskExecutorAgent::fake(['some partial output']);
    PostProcessorAgent::fake([['success' => false, 'summary' => 'The deliverable is missing a summary section.']]);

    $workflow = Workflow::create([
        'input_text' => 'do the important thing',
        'status' => WorkflowStatus::Pending,
    ]);

    PreProcessWorkflow::dispatch($workflow->id);

    $workflow->refresh();

    expect($workflow->status)->toBe(WorkflowStatus::Failed)
        ->and($workflow->post_success)->toBeFalse()
        ->and($workflow->post_summary)->toBe('The deliverable is missing a summary section.');
});
