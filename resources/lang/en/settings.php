<?php

declare(strict_types=1);

return [
    'title' => 'Settings',
    'group' => 'Group',
    'key' => 'Key',
    'fields_count' => 'Fields',
    'updated_at' => 'Updated',
    'save' => 'Save',
    'open' => 'Open',
    'source' => 'Declared by',

    'schema' => [
        'title' => 'Declared schema',
        'type' => 'Type',
        'store' => 'Store',
        'secret' => 'Secret',
    ],

    'saved' => 'Settings saved.',

    'secret' => [
        'reveal' => 'Reveal value',
        'hide' => 'Hide value',
        'placeholder' => 'Leave empty to keep the current value',
        'provider_backed' => 'Stored in the external secret store. Enter a new value to overwrite; leave empty to keep the current one.',
    ],

    'history' => [
        'title' => 'Change history',
        'path' => 'Setting',
        'causer' => 'Changed by',
        'source' => 'Source',
        'old_value' => 'Was',
        'new_value' => 'Now',
        'redacted' => 'Redacted',
        'revert_confirm' => 'Restore this value?',
        'revert' => 'Restore this value',
        'revert_unavailable_redacted' => 'A secret value is never stored, so it cannot be restored.',
        'revert_unavailable_undeclared' => 'This setting is no longer declared.',
    ],

    'empty' => [
        'title' => 'No settings available',
        'description' => 'Nothing has been declared, or you do not have access.',
    ],
];
