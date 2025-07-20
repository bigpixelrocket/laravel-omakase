<?php

declare(strict_types=1);

namespace Bigpixelrocket\LaravelOmakase\Commands;

use Illuminate\Console\Command;

class DbMigrateCommand extends Command
{
    protected $signature = 'db:migrate
        {--database= : The database connection to use}
        {--force : Force the operation to run when in production}
        {--path=* : The path(s) to the migrations files to be executed}
        {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
        {--schema-path= : The path to a schema dump file}
        {--pretend : Dump the SQL queries that would be run}
        {--seed : Indicates if the seed task should be run}
        {--seeder= : The class name of the root seeder}
        {--step : Force the migrations to be run so they can be rolled back individually}
        {--graceful : Return a successful exit code even if an error occurs}
        {--isolated= : Do not run the command if another instance of the command is already running}';

    protected $description = 'Alias for the migrate command - Run the database migrations';

    public function handle(): int
    {
        //
        // Forward to Migrate Command
        // -------------------------------------------------------------------------------

        // Build options array, filtering out null/false values
        $options = array_filter([
            '--database' => $this->option('database'),
            '--force' => $this->option('force'),
            '--path' => $this->option('path'),
            '--realpath' => $this->option('realpath'),
            '--schema-path' => $this->option('schema-path'),
            '--pretend' => $this->option('pretend'),
            '--seed' => $this->option('seed'),
            '--seeder' => $this->option('seeder'),
            '--step' => $this->option('step'),
            '--graceful' => $this->option('graceful'),
            '--isolated' => $this->option('isolated'),
        ], fn ($value) => $this->isValidOption($value));

        return $this->call('migrate', $options);
    }

    /**
     * Determine if the given value is a valid option.
     *
     * @param  mixed  $value
     */
    private function isValidOption($value): bool
    {
        return ! in_array($value, [false, null, []], true);
    }
}
