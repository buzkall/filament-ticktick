# Filament TickTick

A Filament v4 package that integrates TickTick API into your Filament admin panel.

## Requirements

- PHP 8.2 or higher
- Laravel 10.x or 11.x
- Filament 4.x

## Installation

Install the package via composer:

```bash
composer require buzkall/filament-ticktick
```

Publish the package assets (optional):

```bash
php artisan vendor:publish --tag="filament-ticktick-config"
php artisan vendor:publish --tag="filament-ticktick-translations"
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="filament-ticktick-migrations"
php artisan migrate
```

This will create the `ticktick_tasks` table with the following columns:
- `id` - Primary key
- `title` - Task title
- `content` - Task description/content
- `start_date` - Task start date
- `due_date` - Task due date
- `priority` - Priority level (0: None, 1: Low, 3: Medium, 5: High)
- `status` - Task status (0: Active, 2: Completed)
- `project_id` - TickTick project identifier
- `tags` - JSON array of tags
- `ticktick_id` - Unique TickTick task identifier
- `timestamps` - Created at and updated at timestamps

## Configuration

This package uses the `buzkall/laravel-ticktick` package which requires OAuth2 authentication with the TickTick API.

### Getting Your Access Token

1. Visit the [TickTick Developer Portal](https://developer.ticktick.com/)
2. Create a new application
3. Copy your Client ID and Client Secret
4. Follow the OAuth2 flow to obtain an access token (see the [laravel-ticktick package documentation](https://github.com/buzkall/laravel-ticktick#authentication))

### Add Your Access Token

Once you have your access token, add it to your `.env` file:

```env
TICKTICK_ACCESS_TOKEN=your_access_token_here
```

**Note:** For a complete OAuth2 implementation example, refer to the [buzkall/laravel-ticktick package documentation](https://github.com/buzkall/laravel-ticktick). The OAuth flow involves:
1. Redirecting users to TickTick's authorization page
2. Handling the callback with the authorization code
3. Exchanging the code for an access token
4. Storing the access token securely

## Usage

Register the plugin in your Filament panel provider (e.g., `app/Providers/Filament/AdminPanelProvider.php`):

```php
use Buzkall\FilamentTicktick\FilamentTicktickPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            FilamentTicktickPlugin::make(),
        ]);
}
```

## Features

- Manage TickTick tasks directly from your Filament admin panel
- Create, edit, and delete tasks
- Set priorities and due dates
- Organize tasks with tags
- Filter tasks by status and priority
- Full internationalization support (English and Spanish included)
- Type-safe enums for status and priority with automatic badge colors

## Resource Schema

The package uses Filament v4's Schema format for the resource definition, providing a modern and type-safe way to define forms and tables.

### Form Fields

- Title (required)
- Content/Description
- Start Date
- Due Date
- Priority (None, Low, Medium, High)
- Status (Active, Completed)
- Project ID
- Tags

### Table Columns

- Title (searchable, sortable)
- Priority (with badge colors)
- Status (with badge colors)
- Due Date
- Start Date
- Created/Updated timestamps

## Translations

The package includes full translation support for English and Spanish. To publish and customize translations:

```bash
php artisan vendor:publish --tag="filament-ticktick-translations"
```

This will publish the translation files to `lang/vendor/filament-ticktick/` in your application.

### Available Languages

- English (`en`)
- Spanish (`es`)

### Translation Files

- `enums.php` - Status and priority labels
- `resource.php` - Field labels and resource names

To add support for additional languages, simply copy one of the language directories and translate the values.

## License

MIT
