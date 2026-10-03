@props(['title' => null, 'description' => null])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-secondary-200 bg-white p-5 dark:border-secondary-700 dark:bg-secondary-800']) }}>
    @if ($title)
        <h3 class="mb-1 text-base font-semibold text-secondary-900 dark:text-secondary-100">{{ $title }}</h3>
    @endif

    @if ($description)
        <p class="mb-4 text-sm text-secondary-500 dark:text-secondary-400">{{ $description }}</p>
    @endif

    <div @class(['mt-4' => $title && ! $description])>
        {{ $slot }}
    </div>
</div>
