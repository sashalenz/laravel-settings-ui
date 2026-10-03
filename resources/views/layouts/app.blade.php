<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50 dark:bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('settings::settings.title') }} - {{ config('app.name', 'Laravel') }}</title>
    <!-- Tailwind CSS CDN for standalone preview -->
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="h-full text-gray-900 dark:text-gray-100 antialiased">
    <div class="min-h-full">
        <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="{{ route('settings.index') }}" class="text-xl font-bold text-gray-900 dark:text-white">
                        ⚙️ {{ __('settings::settings.title') }}
                    </a>
                </div>
                <nav class="flex items-center space-x-3 text-sm">
                    <a href="{{ route('settings.index') }}" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
                        {{ __('settings::settings.groups') ?? 'Groups' }}
                    </a>
                    <a href="{{ route('settings.history') }}" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
                        {{ __('settings::settings.history.title') ?? 'History' }}
                    </a>
                    <a href="{{ route('settings.schema') }}" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
                        {{ __('settings::settings.schema.title') ?? 'Schema' }}
                    </a>
                </nav>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            @if (session('status'))
                <div class="mb-6 rounded-md bg-green-50 dark:bg-green-900/30 p-4 border border-green-200 dark:border-green-800">
                    <p class="text-sm font-medium text-green-800 dark:text-green-300">{{ session('status') }}</p>
                </div>
            @endif
            @if (session('error'))
                <div class="mb-6 rounded-md bg-red-50 dark:bg-red-900/30 p-4 border border-red-200 dark:border-red-800">
                    <p class="text-sm font-medium text-red-800 dark:text-red-300">{{ session('error') }}</p>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
