# Installation

This guide covers the requirements and installation process for Converse Prism.

## Requirements

Before installing Converse Prism, make sure your project meets these requirements:

- PHP 8.2 or higher
- Laravel 11.0 or 12.0

## Installation

Install Converse Prism via Composer:

```bash
composer require elliottlawson/converse-prism
```

This will automatically install [Converse](https://github.com/elliottlawson/converse) for conversation management.

## Database Migrations

Run the migrations to create the conversation tables:

```bash
php artisan migrate
```

This creates two tables:
- `conversations` - Stores conversation metadata
- `messages` - Stores individual messages

## Next Steps

Once installed, proceed to [Setup](setup.md) to configure your User model and API keys. 