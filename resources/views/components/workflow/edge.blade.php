@props([
    'text' => null,        // hover content (the payload passed across this edge)
    'label' => null,       // small caption under the line
    'state' => 'idle',     // idle | active | done | failed
])

@php
    $stroke = match ($state) {
        'active' => 'text-blue-500',
        'done'   => 'text-emerald-500',
        'failed' => 'text-red-500',
        default  => 'text-zinc-300 dark:text-zinc-600',
    };
    $tip = filled($text) ? $text : '(no content yet)';
@endphp

<div
    class="group relative flex min-w-16 flex-1 cursor-help flex-col items-center justify-center"
    data-tip="{{ $tip }}"
    @mouseenter="showTip($event)"
    @mousemove="moveTip($event)"
    @mouseleave="hideTip()"
>
    <svg class="h-6 w-full {{ $stroke }}" preserveAspectRatio="none" viewBox="0 0 100 24" fill="none">
        <line x1="0" y1="12" x2="92" y2="12" stroke="currentColor" stroke-width="2"
              class="{{ $state === 'active' ? 'edge-flow' : '' }}" />
        <path d="M92 6 L100 12 L92 18 Z" fill="currentColor" />
    </svg>
    @if ($label)
        <span class="pointer-events-none mt-0.5 text-[10px] uppercase tracking-wide text-zinc-400 group-hover:text-zinc-600 dark:group-hover:text-zinc-300">{{ $label }}</span>
    @endif
</div>
