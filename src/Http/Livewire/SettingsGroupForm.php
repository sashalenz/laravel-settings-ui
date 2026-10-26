<?php

declare(strict_types=1);

namespace SashaLenz\SettingsUi\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use SashaLenz\SettingsUi\Contracts\FieldRendererInterface;
use SashaLenz\SettingsUi\Contracts\SettingsAuthorizer;
use SashaLenz\SettingsUi\History\ChangeRecorder;
use SashaLenz\SettingsUi\Resolution\Resolver;
use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\ResolvedField;
use SashaLenz\SettingsUi\Schema\ResolvedGroup;
use SashaLenz\SettingsUi\SettingsManager;

/**
 * Native Livewire form component for editing setting values in a root group.
 * Independent of any proprietary UI packages.
 */
class SettingsGroupForm extends Component
{
    #[Locked]
    public string $group;

    /** @var array<string, mixed> Keyed by relative field path */
    public array $data = [];

    public function mount(string $group): void
    {
        $this->group = $group;

        $resolved = $this->resolvedGroup();
        abort_if($resolved === null, 404);
        abort_unless($this->authorizer()->canView(auth()->user(), $resolved), 403);

        foreach ($this->fieldsInOrder() as $field) {
            Arr::set(
                $this->data,
                $field->relativePath(),
                $field->field->secret ? null : $this->resolver()->get($field->path),
            );
        }

    }

    public function render(): View
    {
        $layout = config('settings.ui.layout', 'settings::layouts.app');

        $view = view('settings::group-form', [
            'tree' => $this->tree(),
            'canManage' => $this->canManage(),
            'title' => $this->title(),
        ]);

        return $layout ? $view->layout($layout) : $view;
    }

    public function renderField(string $wireId): string
    {
        // Strip leading 'data.' or 'form.data.' to get relativePath
        $relativePath = preg_replace('/^(form\.)?data\./', '', $wireId) ?? $wireId;

        $field = null;
        foreach ($this->fieldsInOrder() as $f) {
            if ($f->relativePath() === $relativePath) {
                $field = $f;
                break;
            }
        }

        if ($field === null) {
            return '';
        }

        $wireModel = 'data.'.$relativePath;
        $currentValue = Arr::get($this->data, $relativePath);

        return app(FieldRendererInterface::class)->render($field, $wireModel, $currentValue);
    }

    #[Computed]
    public function tree(): ?ResolvedGroup
    {
        return $this->resolvedGroup();
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->authorizer()->canManage(auth()->user(), $this->resolvedGroup());
    }

    public function title(): string
    {
        $group = $this->resolvedGroup();
        $label = $group?->group->label;

        if ($label === null) {
            return $this->group;
        }

        $translated = __($label);

        return is_string($translated) && $translated !== $label ? $translated : $this->group;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->fieldsInOrder() as $field) {
            $path = 'data.'.$field->relativePath();
            $rules += $field->field->secret
                ? [$path => ['nullable', 'string']]
                : $field->field->validationRules($path);
        }

        return $rules;
    }

    public function save(): void
    {
        abort_unless($this->authorizer()->canManage(auth()->user(), $this->resolvedGroup()), 403);

        $this->validate();

        $resolver = $this->resolver();
        $recorder = app(ChangeRecorder::class);
        $changes = [];

        DB::transaction(function () use ($resolver, &$changes): void {
            foreach ($this->fieldsInOrder() as $field) {
                $submitted = $field->field->normalize(Arr::get($this->data, $field->relativePath()));
                Arr::set($this->data, $field->relativePath(), $submitted);

                if ($field->field->secret && ($submitted === null || $submitted === '')) {
                    continue;
                }

                $current = $resolver->get($field->path);

                if ($current === $submitted) {
                    continue;
                }

                $resolver->put($field->path, $submitted);
                $changes[] = [$field, $current, $submitted];
            }
        });

        foreach ($changes as [$field, $before, $after]) {
            $recorder->record($field, $before, $after, 'ui');
        }

        foreach ($this->fieldsInOrder() as $field) {
            if ($field->field->secret) {
                Arr::set($this->data, $field->relativePath(), null);
            }
        }

        session()->flash('status', __('settings::settings.saved'));
    }

    public function addRow(string $path): void
    {
        $field = $this->collectionField($path);
        $rows = $field->normalize(Arr::get($this->data, $path));
        if ($field->maxItems !== null && count($rows) >= $field->maxItems) {
            return;
        }
        $row = '';
        if ($field->type === 'repeater') {
            $row = [];
            foreach ($field->schema as $child) {
                $row[$child->key] = $child->default;
            }
        }
        $rows[] = $row;
        Arr::set($this->data, $path, $rows);
        $this->resetValidation();
    }

    public function removeRow(string $path, int $index): void
    {
        $field = $this->collectionField($path);
        $rows = $field->normalize(Arr::get($this->data, $path));
        if (! array_key_exists($index, $rows) || count($rows) <= ($field->minItems ?? 0)) {
            return;
        }
        array_splice($rows, $index, 1);
        Arr::set($this->data, $path, array_values($rows));
        $this->resetValidation();
    }

    public function moveRow(string $path, int $from, int $to): void
    {
        $field = $this->collectionField($path);
        $rows = $field->normalize(Arr::get($this->data, $path));
        if (! array_key_exists($from, $rows) || ! array_key_exists($to, $rows)) {
            return;
        }
        $row = array_splice($rows, $from, 1);
        array_splice($rows, $to, 0, $row);
        Arr::set($this->data, $path, array_values($rows));
        $this->resetValidation();
    }

    private function collectionField(string $path): Field
    {
        abort_unless($this->canManage(), 403);
        foreach ($this->fieldsInOrder() as $resolved) {
            if ($resolved->relativePath() === $path && $resolved->field->isCollection()) {
                abort_if($resolved->field->readonly || $resolved->field->hidden, 403);

                return $resolved->field;
            }
        }
        abort(404);
    }

    public function revealSecret(string $path): void
    {
        $field = app(SettingsManager::class)->field($path);

        if ($field === null || ! $field->field->secret) {
            return;
        }

        abort_unless($this->authorizer()->canReveal(auth()->user(), $field), 403);

        $value = $this->resolver()->get($path);
        app(ChangeRecorder::class)->recordReveal($field);

        $this->dispatch('settings-secret-revealed', path: $path, value: (string) ($value ?? ''));
    }

    /** @return list<ResolvedField> */
    private function fieldsInOrder(): array
    {
        $group = $this->resolvedGroup();

        return $group === null ? [] : $group->allFields();
    }

    private function resolvedGroup(): ?ResolvedGroup
    {
        return app(SettingsManager::class)->group($this->group);
    }

    private function resolver(): Resolver
    {
        return app(Resolver::class);
    }

    private function authorizer(): SettingsAuthorizer
    {
        return app(SettingsAuthorizer::class);
    }
}
