@props([
    'variant' => 'default',
])

@php
    $variants = [
        'default' => 'bg-white shadow-sm ring-1 ring-gray-100 dark:bg-gray-800 dark:ring-gray-700',
        'elevated' => 'bg-white shadow-lg ring-1 ring-gray-100 dark:bg-gray-800 dark:ring-gray-700',
        'bordered' => 'bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700',
    ];

    $classes = 'rounded-xl p-6 ' . ($variants[$variant] ?? $variants['default']);
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    @if (isset($header))
        <div class="mb-4">
            {{ $header }}
        </div>
    @endif

    {{ $slot }}

    @if (isset($footer))
        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
            {{ $footer }}
        </div>
    @endif
</div>
