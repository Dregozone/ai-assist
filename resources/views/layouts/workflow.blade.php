<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <style>
            @keyframes edge-flow { to { stroke-dashoffset: -16; } }
            .edge-flow { stroke-dasharray: 6 4; animation: edge-flow 0.6s linear infinite; }
        </style>
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        {{ $slot }}
        @fluxScripts
    </body>
</html>
