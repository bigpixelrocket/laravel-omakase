# Laravel Omakase

An opinionated Laravel package that provides a curated selection of packages and configurations for your next Laravel project. Just like omakase dining where the chef chooses the best ingredients for you, this package installs and configures a thoughtfully selected set of development tools and packages.

## Features

- 🎯 **Curated Package Selection**: Installs popular and well-maintained packages for modern Laravel development
- ⚙️ **Pre-configured Tools**: Copies ready-to-use configuration files for development tools
- 🔧 **Flexible Installation**: Choose to install only specific parts (composer packages, npm packages, or configuration files)
- 📁 **GitHub Actions**: Includes pre-configured workflows for CI/CD
- 🎨 **Code Quality Tools**: Sets up PHPStan, Pint, Pest, and Prettier with sensible defaults

## Requirements

- PHP ^8.4
- Laravel ^12.19

## Installation

Install the package via Composer:

```bash
composer require --dev bigpixelrocket/laravel-omakase
```

The package will automatically register itself via Laravel's package discovery.

## Usage

### Install Everything (Recommended)

Run the omakase command to install all packages and copy all configuration files:

```bash
php artisan laravel:omakase
```

### Selective Installation

You can choose to install only specific parts:

```bash
# Install only Composer packages
php artisan laravel:omakase --composer

# Install only NPM packages
php artisan laravel:omakase --npm

# Copy only configuration files
php artisan laravel:omakase --files

# Force overwrite existing files when copying
php artisan laravel:omakase --files --force
```

### Database Migration Alias

I never really got why the `migrate` command is outside the `db:*` namespace. Maybe it's just muscle memory from my Rails days but I always find myself trying to run `db:migrate` instead of just `migrate`. So this package also provides an alias for Laravel's migrate command:

```bash
# Run database migrations (equivalent to 'php artisan migrate')
php artisan db:migrate

# Pass any migrate options (e.g., --force, --seed, --step, etc.)
php artisan db:migrate --force --seed

# View migrate command help
php artisan db:migrate --migrate-help
```

This `db:migrate` command is a drop-in replacement for Laravel's built-in `migrate` command, accepting all the same arguments and options. The `--migrate-help` flag shows the full help documentation for the underlying `migrate` command.

## What Gets Installed

### Composer Packages

**Production Dependencies:**

- `livewire/flux` - Modern UI component library for Livewire applications
- `livewire/livewire` - Full-stack framework for Laravel
- `spatie/laravel-data` - Powerful data objects for Laravel

**Development Dependencies:**

- `barryvdh/laravel-ide-helper` - IDE helper for Laravel
- `larastan/larastan` - PHPStan for Laravel (automatically runs static analysis after installation)
- `laravel/pint` - Code style fixer (automatically fixes code style after installation)
- `pestphp/pest` - Testing framework

### NPM Packages

**Dependencies:**

- `tailwindcss` - Utility-first CSS framework
- `@tailwindcss/vite` - Tailwind CSS Vite plugin

**Development Dependencies:**

- `prettier` - Code formatter
- `prettier-plugin-blade` - Blade template formatting
- `prettier-plugin-tailwindcss` - Tailwind CSS class sorting

### Configuration Files

The package copies the following configuration files to your project:

- **Code Quality:**

  - `phpstan.neon` - PHPStan configuration
  - `pint.json` - Laravel Pint configuration
  - `.prettierrc` - Prettier configuration

- **Development:**

  - `TESTING.md` - Testing guidelines and best practices
  - `AGENTS.md` - AI agent guidelines
  - `CLAUDE.md` - Claude AI specific guidelines
  - `.cursorrules` - Cursor IDE rules
  - `.cursorignore` - Cursor ignore patterns

- **GitHub Integration:**
  - `.github/workflows/pest.yml` - Pest testing workflow
  - `.github/workflows/phpstan.yml` - PHPStan analysis workflow
  - `.github/workflows/pint.yml` - Code style checking workflow
  - `.github/workflows/dependabot-automerge.yml` - Dependabot auto-merge workflow
  - `.github/dependabot.yml` - Dependabot configuration

## Command Options

### laravel:omakase Command

| Option       | Description                                               |
| ------------ | --------------------------------------------------------- |
| `--composer` | Install only Composer packages                            |
| `--npm`      | Install only NPM packages                                 |
| `--files`    | Copy only configuration files                             |
| `--force`    | Override existing files when copying (use with `--files`) |

### db:migrate Command

| Option           | Description                                                |
| ---------------- | ---------------------------------------------------------- |
| `--migrate-help` | Show help documentation for the underlying migrate command |
| `*`              | Accepts all standard Laravel migrate command options       |

## Post-Installation

### Automatic Code Quality Checks

The package automatically runs code quality tools after installation:

- **Laravel Pint**: Automatically fixes code style issues in your project
- **PHPStan**: Automatically runs static analysis to identify potential issues

These automatic checks help ensure your code follows best practices from the start.

### Additional Setup

Some packages may require additional setup steps:

### Livewire Flux

- Configuration is published automatically during installation
- **Important**: For complete setup including asset publishing and layout configuration, follow the [official Flux installation guide](https://fluxui.dev/docs/installation)
- You'll need to add the `@fluxAppearance` and `@fluxScripts` directives to your layout file
- Consider using the Inter font family for optimal appearance

### Tailwind CSS

- You'll need to import the Tailwind Vite plugin in your `vite.config.js` file
- You'll need to add `@import "tailwindcss";` to your CSS file
- For detailed setup instructions and additional configuration options, see the [official Tailwind CSS Vite guide](https://tailwindcss.com/docs/installation/using-vite)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Lucian Văcăroiu](https://github.com/lucianvacaroiu)
- [Bigpixelrocket](https://bigpixelrocket.com)
