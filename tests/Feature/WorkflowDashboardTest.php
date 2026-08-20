<?php

use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Jobs\PreProcessWorkflow;
use App\Models\Workflow;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

it('renders the workflow dashboard page', function () {
    $this->get('/workflow')
        ->assertOk()
        ->assertSee('AI Workflow Visualiser');
});

it('creates a workflow and dispatches pre-processing on submit', function () {
    Bus::fake();

    Livewire::test('pages::workflow')
        ->set('input', 'Plan a small launch email for a new coffee blend')
        ->call('submit')
        ->assertHasNoErrors();

    expect(Workflow::count())->toBe(1);
    Bus::assertDispatched(PreProcessWorkflow::class);
});

it('renders the full graph for a workflow that has tasks', function () {
    $workflow = Workflow::factory()->create([
        'status' => WorkflowStatus::ExecutingTasks,
        'optimized_prompt' => 'An optimised prompt.',
    ]);
    $workflow->tasks()->create([
        'key' => 't1',
        'title' => 'Draft the copy',
        'description' => 'Write the email body',
        'depends_on' => [],
        'status' => TaskStatus::Running,
    ]);

    Livewire::test('pages::workflow')
        ->set('workflowId', $workflow->id)
        ->assertOk()
        ->assertSee('Draft the copy')
        ->assertSee('PostProcessor');
});

it('shows the output button and result content for a finished run', function () {
    $workflow = Workflow::factory()->create([
        'status' => WorkflowStatus::Succeeded,
        'optimized_prompt' => 'Write a launch email.',
        'post_success' => true,
        'post_summary' => 'The launch email is complete.',
    ]);
    $workflow->tasks()->create([
        'key' => 't1',
        'title' => 'Draft the email',
        'description' => 'Write the body',
        'depends_on' => [],
        'status' => TaskStatus::Completed,
        'output' => 'Subject: Meet our new blend! Dear friend, ...',
        'completed_at' => now(),
    ]);

    Livewire::test('pages::workflow')
        ->set('workflowId', $workflow->id)
        ->assertOk()
        ->assertSee('Output')
        ->assertSee('The launch email is complete.')
        ->assertSee('Meet our new blend!');
});

it('validates that input is required', function () {
    Livewire::test('pages::workflow')
        ->set('input', '')
        ->call('submit')
        ->assertHasErrors('input');

    expect(Workflow::count())->toBe(0);
});
