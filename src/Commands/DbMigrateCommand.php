<?php

declare(strict_types=1);

namespace Bigpixelrocket\LaravelOmakase\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class DbMigrateCommand extends Command
{
    protected $signature = 'db:migrate {--migrate-help : Show help for the migrate command} {--debug : Show debug information about parameters being passed}';

    protected $description = 'Alias for the migrate command - Run the database migrations (use --migrate-help to see migrate options)';

    public function __construct()
    {
        parent::__construct();

        // Allow this command to ignore validation errors for unknown options
        $this->ignoreValidationErrors();
    }

    public function handle(): int
    {
        //
        // Help Command Handling
        // -------------------------------------------------------------------------------

        // Sadly the --help option never reaches the command, so we need a different help param and
        // we can't use $this->call(...) because it doesn't pass the --help option to the command
        // so we use Laravel's Process facade to run the migrate command with --help
        if ($this->option('migrate-help')) {
            try {
                // TTY mode will directly output to the terminal with proper formatting
                $result = Process::tty()->run(['php', 'artisan', 'migrate', '--help']);

                // TTY handles all output directly, so we just return the exit code
                return $result->exitCode() ?? 1;

            } catch (\Throwable) {
                // TTY not supported (like in test environments), fallback to regular process
                $result = Process::run(['php', 'artisan', 'migrate', '--help']);

                // In non-TTY mode, manually handle output based on success/failure
                if ($result->successful()) {
                    $this->output->write($result->output());

                    return 0;
                } else {
                    $this->output->write($result->errorOutput());

                    return $result->exitCode() ?? 1;
                }
            }
        }

        //
        // Delegate to Migrate Command
        // -------------------------------------------------------------------------------
        //
        // This approach:
        // ✅ Works in all contexts (CLI, testing, programmatic calls)
        // ✅ Captures all unknown options by parsing the input string
        // ✅ Uses Laravel's $this->call() method
        // ✅ Doesn't rely on global $argv
        // ✅ Much simpler than reflection or raw Process execution

        // Build the migrate command arguments by reconstructing from input
        $args = [];

        // Add arguments
        foreach ($this->input->getArguments() as $key => $value) {
            if ($key !== 'command' && $value !== null) {
                if (is_array($value)) {
                    $args = array_merge($args, $value);
                } else {
                    $args[] = $value;
                }
            }
        }

        // For options, we'll parse them from the input string to catch unknown ones
        $inputString = (string) $this->input;

        // Extract all options from the input string using regex
        preg_match_all('/--([a-zA-Z0-9-]+)(?:=([^\s]+))?/', $inputString, $matches);

        $parameters = [];
        $isDebugMode = false;

        // Detect debug flag using our regex parsing instead of $this->option('debug')
        //
        // Why we don't use $this->option('debug'):
        // Laravel's option parsing can be order-dependent and inconsistent when combined
        // with ignoreValidationErrors(). If --debug appears after an unknown option,
        // Symfony may fail to parse it properly. Our regex-based detection is more
        // straight-forward and works regardless of option position in the command line.
        for ($i = 0; $i < count($matches[1]); $i++) {
            if ($matches[1][$i] === 'debug') {
                $isDebugMode = true;
                break;
            }
        }

        // Add arguments
        foreach ($args as $arg) {
            $parameters[] = $arg;
        }

        // Add options (excluding our custom ones)
        for ($i = 0; $i < count($matches[1]); $i++) {
            $optionName = $matches[1][$i];
            $optionValue = $matches[2][$i] ?? null;

            // Skip our custom options
            if ($optionName === 'migrate-help' || $optionName === 'debug') {
                continue;
            }

            if ($optionValue !== null && $optionValue !== '') {
                $parameters["--{$optionName}"] = $optionValue;
            } else {
                $parameters["--{$optionName}"] = true;
            }
        }

        // Show debug information if requested (using our regex detection)
        if ($isDebugMode && ! empty($parameters)) {
            $this->line('🐛 Debug: Passing the following parameters to the `migrate` command:');

            // phpcs:ignore
            dump($parameters);
        }

        return $this->call('migrate', $parameters);
    }
}
