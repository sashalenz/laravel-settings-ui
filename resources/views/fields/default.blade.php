@props([
    'resolved',
    'wireModel',
    'currentValue' => null,
])

@php
    $field = $resolved->field;
    $label = $field->label ? __($field->label) : ucfirst(str_replace('_', ' ', $field->key));
    $help = $field->help ? __($field->help) : null;
    $type = $field->type;
    $isSecret = $field->secret;
@endphp

<div class="space-y-1.5" wire:key="field-wrapper-{{ $resolved->path }}">
    @if ($type !== 'boolean')
        <div class="flex items-center justify-between">
            <label for="{{ $wireModel }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $label }}
                @if ($field->isRequired())
                    <span class="text-red-500">*</span>
                @endif
            </label>
            <span class="text-xs text-gray-400 font-mono">{{ $resolved->path }}</span>
        </div>
    @endif

    @if ($isSecret)
        <div x-data="{ revealed: false, showInput: false }" class="space-y-2">
            <div class="flex items-center space-x-2">
                <template x-if="!showInput">
                    <div class="flex-1 flex items-center justify-between bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md px-3 py-2 text-sm">
                        <span class="text-gray-500 font-mono">••••••••••••••••</span>
                        <button type="button" @click="showInput = true" class="text-xs text-primary-600 hover:text-primary-700 font-medium">
                            {{ __('settings::settings.change') ?? 'Change' }}
                        </button>
                    </div>
                </template>
                <template x-if="showInput">
                    <div class="flex-1 flex items-center space-x-2">
                        <input
                            :type="revealed ? 'text' : 'password'"
                            wire:model.defer="{{ $wireModel }}"
                            id="{{ $wireModel }}"
                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                            placeholder="Enter new secret value"
                        />
                        <button type="button" @click="revealed = !revealed" class="px-2.5 py-1.5 text-xs border rounded text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <span x-text="revealed ? 'Hide' : 'Show'"></span>
                        </button>
                        <button type="button" @click="showInput = false" class="px-2.5 py-1.5 text-xs text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                    </div>
                </template>
            </div>
        </div>
    @elseif ($type === 'boolean')
        <div class="flex items-center justify-between py-2">
            <div>
                <label for="{{ $wireModel }}" class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">
                    {{ $label }}
                </label>
                @if ($help)
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
                @endif
            </div>
            <input
                type="checkbox"
                wire:model.defer="{{ $wireModel }}"
                id="{{ $wireModel }}"
                class="h-4 w-4 rounded border-gray-300 dark:border-gray-700 text-primary-600 focus:ring-primary-500 dark:bg-gray-900"
            />
        </div>
    @elseif ($type === 'textarea')
        <textarea
            wire:model.defer="{{ $wireModel }}"
            id="{{ $wireModel }}"
            rows="4"
            class="block w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
            @if ($field->placeholder) placeholder="{{ __($field->placeholder) }}" @endif
        ></textarea>
    @elseif ($type === 'select')
        <select
            wire:model.defer="{{ $wireModel }}"
            id="{{ $wireModel }}"
            class="block w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
        >
            @if ($field->nullable)
                <option value="">-- {{ __('Select...') }} --</option>
            @endif
            @foreach ($field->options as $optVal => $optLabel)
                <option value="{{ $optVal }}">{{ __($optLabel) }}</option>
            @endforeach
        </select>
    @elseif ($type === 'integer' || $type === 'number')
        <input
            type="number"
            step="1"
            wire:model.defer="{{ $wireModel }}"
            id="{{ $wireModel }}"
            class="block w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
        />
    @elseif ($type === 'decimal' || $type === 'money')
        <input
            type="number"
            step="0.01"
            wire:model.defer="{{ $wireModel }}"
            id="{{ $wireModel }}"
            class="block w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
        />
    @else
        <input
            type="text"
            wire:model.defer="{{ $wireModel }}"
            id="{{ $wireModel }}"
            class="block w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
            @if ($field->placeholder) placeholder="{{ __($field->placeholder) }}" @endif
        />
    @endif

    @if ($help && $type !== 'boolean')
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
    @endif

    @error($wireModel)
        <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
    @enderror
</div>
