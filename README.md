# Filament TickTick

A Filament v4/v5 package that integrates TickTick API into your Filament admin panel.

## Requirements

- PHP 8.4 or higher
- Laravel 12.x or 13.x
- Filament 4.x or 5.x

## Installation

Install the package via composer and run the installer:

```bash
composer require arzcode/filament-ticktick
php artisan filament-ticktick:install
```

The installation command does the wiring in one go: it publishes the migration and asks to run it, asks for your
TickTick access token and stores it as `TICKTICK_ACCESS_TOKEN` in `.env` (when it isn't set yet), runs
`php artisan filament:assets` to publish the plugin's styles (re-run it after updating the package), and injects
`FilamentTicktickPlugin::make()` into every `app/Providers/Filament/*PanelProvider.php`. Running it again is safe: a
panel that already registers the plugin is left as-is.

Publish the translations (optional):

```bash
php artisan vendor:publish --tag="filament-ticktick-translations"
```

To do it by hand instead, publish and run the migrations, publish the assets and register the plugin (see [Usage](#usage)):

```bash
php artisan vendor:publish --tag="filament-ticktick-migrations"
php artisan migrate
php artisan filament:assets
```

This will create the `ticktick_tasks` table with the following columns:
- `id` - Primary key
- `parent_id` - Parent task, for sub-tasks
- `title` - Task title
- `content` - Task description/content
- `start_date` - Task start date
- `due_date` - Task due date
- `priority` - Priority level (0: None, 1: Low, 3: Medium, 5: High)
- `status` - Task status (-1: Abandoned, 0: Active, 2: Completed)
- `project_id` - TickTick project identifier
- `tags` - JSON array of tags
- `ticktick_id` - Unique TickTick task identifier
- `timestamps` - Created at and updated at timestamps

### Uninstalling

```bash
php artisan filament-ticktick:uninstall
```

It reverses the install: removes `FilamentTicktickPlugin::make()` from your panel providers and the styles published to
`public/css/arzcode/filament-ticktick`, then asks separately before dropping the `ticktick_tasks` table, deleting the
published migration, deleting the published translations and removing the `TICKTICK_*` entries from `.env`. It finishes
by running `composer remove arzcode/filament-ticktick`.

## Configuration

This package uses the `arzcode/laravel-ticktick` package which requires OAuth2 authentication with the TickTick API.

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

The API client is the one registered by `arzcode/laravel-ticktick`, so every other option (client id, secret, timeout…) lives in its `ticktick` config file (`php artisan vendor:publish --tag="ticktick-config"`).

**Note:** For a complete OAuth2 implementation example, refer to the [arzcode/laravel-ticktick package documentation](https://github.com/buzkall/laravel-ticktick). The OAuth flow involves:
1. Redirecting users to TickTick's authorization page
2. Handling the callback with the authorization code
3. Exchanging the code for an access token
4. Storing the access token securely

## Usage

Register the plugin in your Filament panel provider (e.g., `app/Providers/Filament/AdminPanelProvider.php`):

```php
use Arzcode\FilamentTicktick\FilamentTicktickPlugin;

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
- Create, edit, and delete tasks: every change is sent to TickTick, and the local change is rolled back if the API call fails
- Import the open tasks of the projects you pick (including the inbox) with the "Import from TickTick" action. Sub-tasks are listed inside their parent task, where they can be created, edited and deleted too
- Complete tasks from the table
- Move tasks between projects by changing their project
- Set priorities and due dates
- Organize tasks with tags
- Filter tasks by status and priority
- Full internationalization support (English, Spanish and Catalan included)
- Type-safe enums for status and priority with automatic badge colors

## Resource Schema

The package uses Filament v4's Schema format for the resource definition, providing a modern and type-safe way to define forms and tables.

### Form Fields

- Title (required)
- Content/Description
- Start Date
- Due Date
- Priority (None, Low, Medium, High)
- Status (Abandoned, Active, Completed)
- Project (loaded from your TickTick projects; empty means the inbox)
- Tags

### Table Columns

- Title (searchable, sortable)
- Priority (with badge colors)
- Status (with badge colors)
- Due Date
- Start Date
- Created/Updated timestamps

## Translations

The package includes full translation support for English, Spanish and Catalan. To publish and customize translations:

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

## Testing

```bash
composer test
```

## License

MIT
