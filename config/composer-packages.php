<?php

return [
    'require' => [
        'livewire/livewire' => [
            'commands' => [
                ['php', 'artisan', 'livewire:publish', '--config'],
            ],
        ],
        'livewire/flux',
        'spatie/laravel-data' => [
            'commands' => [
                ['php', 'artisan', 'vendor:publish', '--provider=Spatie\LaravelData\LaravelDataServiceProvider', '--tag=data-config'],
            ],
        ],
    ],
    'require-dev' => [
        'barryvdh/laravel-debugbar' => [
            'commands' => [
                ['php', 'artisan', 'vendor:publish', '--provider="Barryvdh\Debugbar\ServiceProvider"'],
            ],
        ],
        'barryvdh/laravel-ide-helper' => [
            'composer' => [
                'scripts' => [
                    'post-update-cmd' => [
                        'Illuminate\\Foundation\\ComposerScripts::postUpdate',
                        '@php artisan ide-helper:generate',
                        '@php artisan ide-helper:meta',
                        '@php artisan ide-helper:models --nowrite',
                    ],
                ],
            ],
            'commands' => [
                ['php', 'artisan', 'ide-helper:generate'],
                ['php', 'artisan', 'ide-helper:meta'],
                ['php', 'artisan', 'ide-helper:models', '--nowrite'],
            ],
        ],
        'beyondcode/laravel-query-detector' => [
        ],
        'rector/rector' => [
            'optional_commands' => [
                ['vendor/bin/rector'],
            ],
        ],
        'laravel/pint' => [
            'optional_commands' => [
                ['vendor/bin/pint', '--repair'],
            ],
        ],
        'larastan/larastan' => [
            'optional_commands' => [
                ['vendor/bin/phpstan', 'analyse'],
            ],
        ],
        'pestphp/pest',
        'roave/security-advisories:dev-latest',
    ],
];
