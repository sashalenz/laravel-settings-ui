<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $title }}</h1>
        <div class="w-72">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search groups..."
                class="block w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-3 py-2"
            />
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-md border border-gray-200 dark:border-gray-700">
        <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse ($rows as $row)
                <li>
                    <a href="{{ SashaLenz\SettingsUi\Support\Routes::group($row->path) }}" class="block hover:bg-gray-50 dark:hover:bg-gray-700/50 px-4 py-4 sm:px-6 transition duration-150 ease-in-out">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <span class="text-lg">📁</span>
                                <div>
                                    <p class="text-sm font-semibold text-primary-600 dark:text-primary-400 truncate">
                                        {{ __($row->label_key) !== $row->label_key ? __($row->label_key) : $row->path }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                        {{ $row->path }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                    {{ $row->source }}
                                </span>
                                <span class="text-gray-400 text-sm font-semibold">→</span>
                            </div>
                        </div>
                    </a>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                    No settings groups found.
                </li>
            @endforelse
        </ul>
    </div>

    @if ($rows->hasPages())
        <div>
            {{ $rows->links() }}
        </div>
    @endif
</div>
