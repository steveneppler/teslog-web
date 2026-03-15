# Contributing to Teslog

Teslog is a Tesla vehicle data logging platform. Contributions are welcome.

## Prerequisites

- PHP 8.2+
- Laravel 11
- Composer
- Node.js (for frontend assets)
- SQLite or MySQL

## Setup

```bash
git clone <repo-url>
cd teslog-web
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
```

## Running Tests

```bash
php artisan test
```

## Code Style

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards
- Use Laravel conventions for naming, structure, and patterns
- Livewire components should use `wire:ignore` on JS-managed DOM elements and `@script` blocks for JavaScript interop

## Submitting Changes

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-change`)
3. Write tests for new functionality
4. Ensure all tests pass (`php artisan test`)
5. Commit with a clear message
6. Open a pull request against `main`

## Reporting Issues

Open an issue with steps to reproduce, expected behavior, and actual behavior.
