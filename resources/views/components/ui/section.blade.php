@props([
    'bg' => 'white',
    'padding' => 'py-16 md:py-24',
    'container' => true,
])

@php
    $bgClasses = match($bg) {
        'white' => 'bg-white dark:bg-gray-900',
        'gray' => 'bg-gray-50 dark:bg-gray-800',
        'primary' => 'bg-emerald-600 dark:bg-emerald-700',
        'dark' => 'bg-gray-900 dark:bg-gray-950',
        default => $bg,
    };
@endphp

<section {{ $attributes->merge(['class' => $bgClasses . ' ' . $padding]) }}>
    @if ($container)
        <div class="container-lg">
    @endif

    @if (isset($header))
        <div class="text-center mb-12">
            {{ $header }}
        </div>
    @endif

    {{ $slot }}

    @if ($container)
        </div>
    @endif
</section>
