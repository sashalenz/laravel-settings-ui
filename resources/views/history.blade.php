<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $title }}</h1>
        <div class="w-72">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search history by path..."
                class="block w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm px-3 py-2"
            />
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-md border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Date</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Setting Key</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Old Value</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">New Value</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-300">Source</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-300">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($changes as $change)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $change->created_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3 font-mono font-medium text-gray-900 dark:text-gray-100">{{ $change->path }}</td>
                        <td class="px-4 py-3 text-gray-500 max-w-xs truncate font-mono">
                            @if ($change->redacted)
                                <span class="text-gray-400">••••••••</span>
                            @else
                                {{ is_scalar($change->old_value) ? (string) $change->old_value : json_encode($change->old_value) }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100 max-w-xs truncate font-mono">
                            @if ($change->redacted)
                                <span class="text-gray-400">••••••••</span>
                            @else
                                {{ is_scalar($change->new_value) ? (string) $change->new_value : json_encode($change->new_value) }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs uppercase">{{ $change->source }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if (! $change->redacted)
                                <button
                                    type="button"
                                    wire:click="revert({{ $change->id }})"
                                    wire:confirm="Are you sure you want to revert this setting to its old value?"
                                    class="text-xs text-primary-600 hover:text-primary-800 dark:text-primary-400 font-semibold"
                                >
                                    Revert
                                </button>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500 text-sm">
                            No setting changes recorded yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($changes->hasPages())
        <div>
            {{ $changes->links() }}
        </div>
    @endif
</div>
