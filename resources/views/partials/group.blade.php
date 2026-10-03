{{--
    One node of the tree. `level` 1 draws a card; deeper levels draw a nested
    section, recursively, so arbitrary depth renders without a special case.
--}}
@php($label = $node->group->label ? __($node->group->label) : $node->group->name)

@if ($level === 1)
    <x-settings::card :title="$label" :description="$node->group->description ? __($node->group->description) : null">
        @foreach ($node->fields as $resolved)
            @include('settings::partials.field', ['resolved' => $resolved])
        @endforeach

        @foreach ($node->groups as $child)
            @include('settings::partials.group', ['node' => $child, 'level' => $level + 1])
        @endforeach
    </x-settings::card>
@else
    <section @class([
        'mt-4 border-l-2 border-secondary-200 pl-4 dark:border-secondary-700',
    ])>
        <h4 @class([
            'mb-2 font-medium text-secondary-700 dark:text-secondary-300',
            'text-sm' => $level === 2,
            'text-xs uppercase tracking-wide' => $level > 2,
        ])>{{ $label }}</h4>

        @foreach ($node->fields as $resolved)
            @include('settings::partials.field', ['resolved' => $resolved])
        @endforeach

        @foreach ($node->groups as $child)
            @include('settings::partials.group', ['node' => $child, 'level' => $level + 1])
        @endforeach
    </section>
@endif
