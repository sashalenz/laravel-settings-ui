<?php

declare(strict_types=1);

use SashaLenz\SettingsUi\Schema\Field;
use SashaLenz\SettingsUi\Schema\FieldType;

it('maps built-in types onto the values the host table already stores', function (): void {
    // settings:import reads a20's setting_fields.type column directly, so these
    // backed values are a migration contract, not cosmetics.
    expect(Field::integer('a')->type)->toBe('number')
        ->and(Field::model('a', 'App\\Model')->type)->toBe('wire_select')
        ->and(Field::tree('a', 'App\\Model')->type)->toBe('nested_set')
        ->and(Field::keyValue('a')->type)->toBe('key_value');
});

it('parses pipe rules into a list', function (): void {
    expect(Field::integer('a')->rules('required|integer|min:1')->ruleList())
        ->toBe(['required', 'integer', 'min:1']);
});

it('defaults to nullable when no rules are declared', function (): void {
    expect(Field::text('a')->ruleList())->toBe(['nullable']);
});

it('detects required-ness for the UI marker', function (): void {
    expect(Field::integer('a')->rules('required|integer')->isRequired())->toBeTrue()
        ->and(Field::integer('a')->rules('required_if:form.b,1')->isRequired())->toBeTrue()
        ->and(Field::integer('a')->rules('nullable|integer')->isRequired())->toBeFalse();
});

it('treats encrypted as a kind of secret', function (): void {
    $field = Field::text('token')->encrypted();

    expect($field->encrypted)->toBeTrue()
        ->and($field->secret)->toBeTrue();
});

it('routes a provider-bound field to the secrets store', function (): void {
    $field = Field::text('token')->at('a20/settings', 'edge_git_token');

    expect($field->isProviderBacked())->toBeTrue()
        ->and($field->secret)->toBeTrue()
        ->and($field->store)->toBe('secrets')
        ->and($field->secretRef)->toBe('a20/settings')
        ->and($field->secretField)->toBe('edge_git_token');
});

it('keeps an explicit store when one was already chosen', function (): void {
    $field = Field::text('token')->store('custom')->at('ref');

    expect($field->store)->toBe('custom');
});

it('exposes options and model class for the picker types', function (): void {
    expect(FieldType::Select->needsOptions())->toBeTrue()
        ->and(FieldType::Model->needsModelClass())->toBeTrue()
        ->and(FieldType::Tree->needsModelClass())->toBeTrue()
        ->and(FieldType::Text->needsOptions())->toBeFalse();
});

it('carries a custom type name through untouched', function (): void {
    $field = Field::custom('a', 'colour_picker');

    expect($field->type)->toBe('colour_picker')
        ->and($field->fieldType())->toBeNull();
});
