## What Gets Installed

### Composer Packages

**Production Dependencies:**

- `livewire/flux` - Modern UI component library for Livewire applications
- `livewire/livewire` - Full-stack framework for Laravel
- `spatie/laravel-data` - Powerful data objects for Laravel

**Development Dependencies:**

- `barryvdh/laravel-ide-helper` - IDE helper for Laravel
- `larastan/larastan` - PHPStan for Laravel
- `laravel/pint` - Code style fixer
- `pestphp/pest` - Testing framework
- `soloterm/solo` - Terminal utilities

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
  - `.github/workflows/release.yml` - Release automation workflow
  - `.github/workflows/dependabot-automerge.yml` - Dependabot auto-merge workflow
  - `.github/dependabot.yml` - Dependabot configuration

## Command Options

| Option       | Description                                               |
| ------------ | --------------------------------------------------------- |
| `--composer` | Install only Composer packages                            |
| `--npm`      | Install only NPM packages                                 |
| `--files`    | Copy only configuration files                             |
| `--force`    | Override existing files when copying (use with `--files`) |

## Post-Installation

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
