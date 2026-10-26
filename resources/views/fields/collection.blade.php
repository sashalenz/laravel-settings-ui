@php
    $field = $resolved->field;
    $rows = is_array($currentValue) ? array_values($currentValue) : [];
    $path = $resolved->relativePath();
    $buttonClass = 'px-2.5 py-1.5 text-xs border rounded text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-50';
@endphp

<fieldset class="space-y-3" wire:key="field-wrapper-{{ $resolved->path }}" @disabled($field->readonly)>
    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ $field->label ? __($field->label) : ucfirst(str_replace('_', ' ', $field->key)) }}
    </legend>
    @foreach ($rows as $index => $row)
        <div class="space-y-3 rounded-md border border-gray-300 dark:border-gray-700 p-3" wire:key="{{ $wireModel }}-row-{{ $index }}-{{ md5(serialize($row)) }}">
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __($field->itemLabel ?? 'Item') }} {{ $index + 1 }}</span>
                <div class="flex gap-2">
                    <button type="button" class="{{ $buttonClass }}" wire:click="moveRow('{{ $path }}', {{ $index }}, {{ $index - 1 }})" @disabled($index === 0)>{{ __('Move up') }}</button>
                    <button type="button" class="{{ $buttonClass }}" wire:click="moveRow('{{ $path }}', {{ $index }}, {{ $index + 1 }})" @disabled($index === count($rows) - 1)>{{ __('Move down') }}</button>
                    <button type="button" class="{{ $buttonClass }}" wire:click="removeRow('{{ $path }}', {{ $index }})" @disabled(count($rows) <= ($field->minItems ?? 0))>{{ __('Remove') }}</button>
                </div>
            </div>
            @php
                $children = $field->type === 'list'
                    ? [\SashaLenz\SettingsUi\Schema\Field::text('value')->label($field->itemLabel ?? 'Value')]
                    : $field->schema;
            @endphp
            @foreach ($children as $child)
                @php
                    $childPath = $wireModel.'.'.$index.($field->type === 'list' ? '' : '.'.$child->key);
                    $childResolved = new \SashaLenz\SettingsUi\Schema\ResolvedField(
                        $resolved->path.'.'.$index.'.'.$child->key, $resolved->root,
                        $resolved->path, $child, $resolved->source,
                    );
                @endphp
                {!! app(\SashaLenz\SettingsUi\Contracts\FieldRendererInterface::class)->render($childResolved, $childPath, $field->type === 'list' ? $row : data_get($row, $child->key)) !!}
            @endforeach
            @error($wireModel.'.'.$index)
                <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    @endforeach
    <button type="button" class="{{ $buttonClass }}" wire:click="addRow('{{ $path }}')" @disabled($field->maxItems !== null && count($rows) >= $field->maxItems)>{{ __('Add item') }}</button>
    @if ($field->help)
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __($field->help) }}</p>
    @endif
    @error($wireModel)
        <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</fieldset>
