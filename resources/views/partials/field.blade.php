{{-- One field, looked up by its wire id in the form's rendered field set. --}}
@php($wireId = 'data.'.$resolved->relativePath())

@if (! $resolved->field->hidden)
    <div class="mb-4" wire:key="settings-field-{{ $resolved->path }}">
        {!! $this->renderField($wireId) !!}
    </div>
@endif
