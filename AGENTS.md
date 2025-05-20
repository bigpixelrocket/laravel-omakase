# Repository Overview

This repository contains a Laravel package that installs a set of development tools and configuration files into a Laravel project. The package registers an `OmakaseServiceProvider` which, when running in the console, provides a single command: `OmakaseCommand`.

## General Structure

- **composer.json** defines the package dependencies and registers the service provider so Laravel can auto-load it.
- **src/OmakaseServiceProvider.php** registers the console command when the application is running in the console.
- **src/Commands/OmakaseCommand.php** is the main entry point. It exposes several options (`--composer`, `--npm`, `--files`, and `--force`) and orchestrates installation of Composer and NPM packages and copies files from the `dist/` directory.
- **dist/** contains example configuration files (e.g. `phpstan.neon`, `pint.json`) that can be copied into a project.
- **tests/** contains Pest tests with Testbench to verify command behaviour.
- **GitHub Actions** run Pint, PHPStan and Pest to keep the code clean.

## Important Things to Know

1. Running `php artisan laravel:omakase` installs composer packages, npm packages and copies the files. You can use options to run only parts of the process.
2. The command uses Symfony\`Process` to run external processes for installs and other setup tasks.
3. Tests rely on Testbench to boot a minimal Laravel application so the package can run in isolation.
4. Coding standards are enforced via Pint configuration in `pint.json` and PHPStan rules in `phpstan.neon`.

## Pointers for Learning Next

- Review how `installPackages`, `execCommands` and `copyFiles` are implemented in `OmakaseCommand` to understand the installation process.
- Explore Testbench and Pest to see how Laravel packages are tested.
- Extend the package by adding more template files inside `dist/` or by customising the command options.

## Running Tests

Use PHP **8.2** or higher and run:

```bash
php vendor/bin/pest
```

This executes the test suite using Pest.

## Commit Guidelines

Use the [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/#summary) standard when pushing code. This makes your commit history easy to read and allows automatic changelog generation.
