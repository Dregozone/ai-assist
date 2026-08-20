<?php

use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Jobs\PreProcessWorkflow;
use App\Models\Workflow;
use App\Models\WorkflowTask;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.workflow')] #[Title('AI Workflow Visualiser')] class extends Component
{
    #[Validate('required|string|min:3|max:4000')]
    public string $input = '';

    public ?int $workflowId = null;

    /**
     * Kick off a new workflow run for the entered text.
     */
    public function submit(): void
    {
        $this->validate();

        $workflow = Workflow::create([
            'input_text' => $this->input,
            'status' => WorkflowStatus::Pending,
        ]);

        $this->workflowId = $workflow->id;

        PreProcessWorkflow::dispatch($workflow->id);
    }

    /**
     * Load a previous run into the dashboard.
     */
    public function loadWorkflow(int $id): void
    {
        $this->workflowId = $id;
    }

    /**
     * Clear the dashboard to start a fresh run.
     */
    public function startNew(): void
    {
        $this->reset('workflowId', 'input');
    }

    /**
     * Subscribe to live updates for the active workflow only.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        if ($this->workflowId === null) {
            return [];
        }

        return [
            "echo:workflow.{$this->workflowId},.workflow.updated" => '$refresh',
        ];
    }

    #[Computed]
    public function workflow(): ?Workflow
    {
        return $this->workflowId
            ? Workflow::with('tasks')->find($this->workflowId)
            : null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Workflow>
     */
    #[Computed]
    public function recent()
    {
        return Workflow::latest()->limit(8)->get();
    }

    public function preState(): string
    {
        $w = $this->workflow;

        return match (true) {
            ! $w => 'idle',
            $w->status === WorkflowStatus::PreProcessing => 'active',
            filled($w->optimized_prompt) => 'done',
            $w->status === WorkflowStatus::Failed => 'failed',
            default => 'idle',
        };
    }

    public function processorState(): string
    {
        $w = $this->workflow;

        return match (true) {
            ! $w => 'idle',
            $w->status === WorkflowStatus::Processing => 'active',
            $w->tasks->isNotEmpty() => 'done',
            $w->status === WorkflowStatus::Failed && filled($w->optimized_prompt) => 'failed',
            default => 'idle',
        };
    }

    public function postState(): string
    {
        $w = $this->workflow;

        return match (true) {
            ! $w => 'idle',
            $w->status === WorkflowStatus::PostProcessing => 'active',
            $w->post_success !== null => $w->post_success ? 'done' : 'failed',
            default => 'idle',
        };
    }

    public function resultState(): string
    {
        return match ($this->workflow?->status) {
            WorkflowStatus::Succeeded => 'success',
            WorkflowStatus::Failed => 'failed',
            default => 'idle',
        };
    }

    public function edgeInputState(): string
    {
        $w = $this->workflow;

        return match (true) {
            ! $w => 'idle',
            $w->status === WorkflowStatus::PreProcessing => 'active',
            filled($w->optimized_prompt) => 'done',
            $w->status === WorkflowStatus::Failed => 'failed',
            default => 'done',
        };
    }

    public function edgePromptState(): string
    {
        $w = $this->workflow;

        return match (true) {
            ! $w || ! filled($w->optimized_prompt) => 'idle',
            $w->status === WorkflowStatus::Processing => 'active',
            $w->tasks->isNotEmpty() => 'done',
            $w->status === WorkflowStatus::Failed => 'failed',
            default => 'done',
        };
    }

    public function edgeSummaryState(): string
    {
        $w = $this->workflow;

        return match (true) {
            ! $w => 'idle',
            $w->status === WorkflowStatus::PostProcessing => 'active',
            $w->post_success !== null => $w->post_success ? 'done' : 'failed',
            $w->status === WorkflowStatus::Failed => 'failed',
            default => 'idle',
        };
    }

    public function taskNodeState(WorkflowTask $task): string
    {
        return match ($task->status) {
            TaskStatus::Running => 'active',
            TaskStatus::Queued => 'queued',
            TaskStatus::Completed => 'done',
            TaskStatus::Failed => 'failed',
            default => 'idle',
        };
    }

    public function edgeToTaskState(WorkflowTask $task): string
    {
        return match ($task->status) {
            TaskStatus::Queued => 'active',
            TaskStatus::Running, TaskStatus::Completed => 'done',
            TaskStatus::Failed => $task->started_at ? 'done' : 'idle',
            default => 'idle',
        };
    }

    public function edgeFromTaskState(WorkflowTask $task): string
    {
        return match ($task->status) {
            TaskStatus::Completed => 'done',
            TaskStatus::Failed => 'failed',
            TaskStatus::Running => 'active',
            default => 'idle',
        };
    }

    public function progressPercent(): int
    {
        $w = $this->workflow;

        if (! $w) {
            return 0;
        }

        return match ($w->status) {
            WorkflowStatus::Pending => 5,
            WorkflowStatus::PreProcessing => 20,
            WorkflowStatus::Processing => 35,
            WorkflowStatus::ExecutingTasks => $w->tasks->isEmpty()
                ? 45
                : (int) round(45 + 40 * ($w->tasks->whereIn('status', [TaskStatus::Completed, TaskStatus::Failed])->count() / max($w->tasks->count(), 1))),
            WorkflowStatus::PostProcessing => 90,
            WorkflowStatus::Succeeded, WorkflowStatus::Failed => 100,
        };
    }
}; ?>

<div class="mx-auto min-h-screen w-full max-w-7xl px-4 py-8"
     x-data="{
        tip: null, x: 0, y: 0,
        showTip(e) { this.tip = e.currentTarget.dataset.tip || '(no content yet)'; this.move(e); },
        move(e) { this.x = e.clientX; this.y = e.clientY; },
        hideTip() { this.tip = null; }
     }">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">AI Workflow Visualiser</flux:heading>
            <flux:subheading>Watch your request flow through the multi-agent pipeline in real time.</flux:subheading>
        </div>
        @if ($this->workflow)
            <flux:button size="sm" variant="ghost" wire:click="startNew" icon="plus">New run</flux:button>
        @endif
    </div>

    {{-- Input --}}
    <form wire:submit="submit" class="mb-8 flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <flux:textarea
            wire:model="input"
            rows="3"
            placeholder="Describe what you want done, e.g. “Plan a small launch email for a new coffee blend”"
            :disabled="$this->workflow && ! $this->workflow->status->isTerminal()"
        />
        <div class="flex items-center justify-between">
            <flux:text size="sm" class="text-zinc-500">Each step runs on the queue and streams back over Reverb.</flux:text>
            <flux:button type="submit" variant="primary" icon="sparkles"
                         :disabled="$this->workflow && ! $this->workflow->status->isTerminal()">
                Run workflow
            </flux:button>
        </div>
    </form>

    @php($w = $this->workflow)

    {{-- Progress bar --}}
    @if ($w)
        <div class="mb-6">
            <div class="mb-2 flex items-center justify-between text-sm">
                <flux:badge :color="$w->status === WorkflowStatus::Failed ? 'red' : ($w->status === WorkflowStatus::Succeeded ? 'green' : 'blue')">
                    {{ $w->status->label() }}
                </flux:badge>
                <span class="text-zinc-500">
                    {{ $w->tasks->whereIn('status', [TaskStatus::Completed, TaskStatus::Failed])->count() }}/{{ $w->tasks->count() }} tasks
                </span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                <div class="h-full rounded-full transition-all duration-700 ease-out
                            {{ $w->status === WorkflowStatus::Failed ? 'bg-red-500' : ($w->status === WorkflowStatus::Succeeded ? 'bg-emerald-500' : 'bg-blue-500') }}"
                     style="width: {{ $this->progressPercent() }}%"></div>
            </div>
        </div>
    @endif

    {{-- Graph --}}
    <div @if ($w && ! $w->status->isTerminal()) wire:poll.2000ms @endif
         class="overflow-x-auto rounded-xl border border-zinc-200 bg-white/50 p-6 dark:border-zinc-700 dark:bg-zinc-800/40">
        <div class="flex min-w-max flex-nowrap items-center justify-center gap-1">

            <x-workflow.node title="You" icon="🧑" :subtitle="$w ? Str::limit($w->input_text, 22) : 'your request'"
                             :state="$w ? 'done' : 'idle'" />

            <x-workflow.edge :text="$w?->input_text" label="input" :state="$this->edgeInputState()" />

            <x-workflow.node title="PreProcessor" icon="✨" subtitle="optimise prompt" :state="$this->preState()" />

            <x-workflow.edge :text="$w?->optimized_prompt" label="prompt" :state="$this->edgePromptState()" />

            <x-workflow.node title="Processor" icon="🧩" subtitle="plan tasks" :state="$this->processorState()" />

            {{-- Task fan-out --}}
            <div class="flex flex-col gap-3">
                @forelse ($w?->tasks ?? [] as $task)
                    <div class="flex items-center gap-1">
                        <x-workflow.edge class="w-14 !min-w-0 !flex-none" :text="$task->description"
                                         :state="$this->edgeToTaskState($task)" />
                        <x-workflow.node class="!w-40" :title="$task->title" icon="⚙️"
                                         :subtitle="$task->status->label()" :state="$this->taskNodeState($task)" />
                        <x-workflow.edge class="w-14 !min-w-0 !flex-none" :text="$task->output"
                                         :state="$this->edgeFromTaskState($task)" />
                    </div>
                @empty
                    <x-workflow.node title="Tasks" icon="⚙️" subtitle="awaiting plan"
                                     :state="$this->processorState() === 'active' ? 'queued' : 'idle'" />
                @endforelse
            </div>

            <x-workflow.node title="PostProcessor" icon="🔎" subtitle="verify outcome" :state="$this->postState()" />

            <x-workflow.edge :text="$w?->post_summary" label="result" :state="$this->edgeSummaryState()" />

            <x-workflow.node title="Outcome" :icon="$this->resultState() === 'success' ? '🎉' : ($this->resultState() === 'failed' ? '⚠️' : '🏁')"
                             :subtitle="null" :state="$this->resultState()">
                @if ($w && $w->post_summary)
                    <p class="mt-2 border-t border-zinc-100 pt-2 text-left text-xs text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                        {{ $w->post_summary }}
                    </p>
                @elseif ($w && $w->status === WorkflowStatus::Failed && $w->error)
                    <p class="mt-2 border-t border-zinc-100 pt-2 text-left text-xs text-red-600 dark:border-zinc-700 dark:text-red-400">
                        {{ Str::limit($w->error, 120) }}
                    </p>
                @endif
            </x-workflow.node>
        </div>

        <p class="mt-4 text-center text-xs text-zinc-400">Hover any connecting line to see the exact text passed between steps.</p>
    </div>

    {{-- Recent runs --}}
    @if ($this->recent->isNotEmpty())
        <div class="mt-8">
            <flux:heading size="sm" class="mb-2">Recent runs</flux:heading>
            <div class="flex flex-col divide-y divide-zinc-100 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($this->recent as $run)
                    <button type="button" wire:click="loadWorkflow({{ $run->id }})"
                            class="flex items-center justify-between gap-4 px-4 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800
                                   {{ $run->id === $this->workflowId ? 'bg-zinc-50 dark:bg-zinc-800' : '' }}">
                        <span class="truncate text-zinc-700 dark:text-zinc-200">{{ Str::limit($run->input_text, 70) }}</span>
                        <flux:badge size="sm" :color="$run->status === WorkflowStatus::Failed ? 'red' : ($run->status === WorkflowStatus::Succeeded ? 'green' : 'zinc')">
                            {{ $run->status->label() }}
                        </flux:badge>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Shared hover tooltip --}}
    <template x-if="tip">
        <div class="pointer-events-none fixed z-50 max-w-md whitespace-pre-wrap rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs leading-relaxed text-zinc-100 shadow-xl"
             :style="`left: ${Math.min(x + 16, window.innerWidth - 340)}px; top: ${y + 16}px`"
             x-text="tip"></div>
    </template>
</div>
