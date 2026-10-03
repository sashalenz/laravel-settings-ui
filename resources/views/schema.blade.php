<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $title }}</h1>
        <div class="w-72">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search declared keys..."
                class="block w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-3 py-2"
            />
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-md border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Key Path</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Type</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Store</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Secret?</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Source</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($fields as $field)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="px-4 py-3 font-mono font-medium text-gray-900 dark:text-gray-100">{{ $field->path }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300 font-mono text-xs">{{ $field->type }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300 text-xs">{{ $field->store }}</td>
                        <td class="px-4 py-3">
                            @if ($field->is_secret)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                    🔒 Secret
                                </span>
                            @else
                                <span class="text-gray-400 text-xs">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs truncate max-w-xs">{{ $field->source }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 text-sm">
                            No declared fields found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($fields->hasPages())
        <div>
            {{ $fields->links() }}
        </div>
    @endif
</div>
