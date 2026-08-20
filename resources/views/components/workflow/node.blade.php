@props([
    'title',
    'subtitle' => null,
    'icon' => '●',
    'state' => 'idle', // idle | queued | active | done | failed | success
])

@php
    $ring = match ($state) {
        'active'  => 'border-blue-400 ring-2 ring-blue-400/50 shadow-lg shadow-blue-500/20',
        'queued'  => 'border-amber-300 dark:border-amber-500/60',
        'done'    => 'border-emerald-400/70',
        'success' => 'border-emerald-400 ring-2 ring-emerald-400/50',
        'failed'  => 'border-red-400 ring-2 ring-red-400/40',
        default   => 'border-zinc-200 dark:border-zinc-700',
    };
    $badge = match ($state) {
        'active', 'queued' => null,
        'done', 'success'  => '✓',
        'failed'           => '✕',
        default            => null,
    };
    $badgeColor = $state === 'failed' ? 'bg-red-500' : 'bg-emerald-500';
@endphp

<div {{ $attributes->class([
    'relative w-44 shrink-0 rounded-xl border bg-white p-4 text-center transition-all duration-500 dark:bg-zinc-800',
    $ring,
    'animate-pulse' => $state === 'active',
]) }}>
    <div class="mb-1 text-2xl leading-none">{{ $icon }}</div>
    <div class="text-sm font-semibold">{{ $title }}</div>
    @if ($subtitle)
        <div class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $subtitle }}</div>
    @endif

    @if ($state === 'active' || $state === 'queued')
        <div class="mt-2 flex justify-center">
            <svg class="size-4 animate-spin {{ $state === 'queued' ? 'text-amber-500' : 'text-blue-500' }}" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4z" />
            </svg>
        </div>
    @endif

    @if ($badge)
        <span class="absolute -right-2 -top-2 flex size-5 items-center justify-center rounded-full text-xs font-bold text-white {{ $badgeColor }}">{{ $badge }}</span>
    @endif

    {{ $slot }}
</div>
