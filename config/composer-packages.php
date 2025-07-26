<?php

return [
    'require' => [
        'livewire/livewire' => [
            'commands' => [
                ['php', 'artisan', 'livewire:publish', '--config'],
            ],
        ],
        'livewire/flux' => [
            'post_dist_commands' => [
                ['php', 'artisan', 'flux:activate'],
            ],
        ],
        'spatie/laravel-data' => [
            'commands' => [
                ['php', 'artisan', 'vendor:publish', '--provider=Spatie\LaravelData\LaravelDataServiceProvider', '--tag=data-config'],
            ],
        ],
    ],
    'require-dev' => [
        'barryvdh/laravel-debugbar' => [
            'commands' => [
                ['php', 'artisan', 'vendor:publish', '--provider=Barryvdh\Debugbar\ServiceProvider'],
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
            'post_dist_commands' => [
                ['vendor/bin/rector'],
            ],
        ],
        'laravel/pint' => [
            'post_dist_commands' => [
                ['vendor/bin/pint', '--repair'],
            ],
        ],
        'larastan/larastan' => [
            'post_dist_commands' => [
                ['vendor/bin/phpstan', 'analyse'],
            ],
        ],
        'pestphp/pest' => [
            'post_dist_commands' => [
                ['php', 'artisan', 'key:generate', '--env=testing', '--force'],
            ],
        ],
        'roave/security-advisories:dev-latest',
    ],
];
