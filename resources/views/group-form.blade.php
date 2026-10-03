{{--
    Group editor.

    Renders the declared tree: a root's own fields go in a lead card, each
    first-level nested group becomes its own card, and anything deeper recurses
    as a titled section inside that card. The recursion is not decoration — the
    real data reaches three nested levels, so a template that special-cased
    "two deep" would have been wrong from the first run.
--}}
<form wire:submit="save" class="space-y-6">
    @if ($this->tree)
        @php($tree = $this->tree)

        @if (count($tree->fields))
            <x-settings::card :title="null">
                @foreach ($tree->fields as $resolved)
                    @include('settings::partials.field', ['resolved' => $resolved])
                @endforeach
            </x-settings::card>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach ($tree->groups as $child)
                <div @class(['lg:col-span-2' => $child->group->wide])>
                    @include('settings::partials.group', ['node' => $child, 'level' => 1])
                </div>
            @endforeach
        </div>
    @endif

    @if ($this->canManage)
        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-white">
                {{ __('settings::settings.save') }}
            </button>
        </div>
    @endif
</form>
