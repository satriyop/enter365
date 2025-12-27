<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Enter365') }} - Kelola Proyek & Keuangan Bisnis Anda</title>
    <meta name="description" content="Platform ERP terintegrasi untuk manufaktur panel listrik dan kontraktor EPC solar panel. Kelola proyek, inventori, dan keuangan dalam satu sistem.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 antialiased dark:bg-gray-900 dark:text-gray-100">
    {{ $slot }}
</body>
</html>
