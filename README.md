# Filament Unsaved Changes Bar

A floating Shopify / Laravel Cloud-style **"Unsaved changes · Discard · Save"** bar for Filament panel forms.

- Filament 4 and 5 (Livewire 3 and 4), PHP 8.2+
- Dirty detection uses the same hash as Filament's built-in `unsavedChangesAlerts()`, so both agree. The panel option is **not** required.
- In SPA mode, clicking a link while dirty shakes the bar instead of opening a `confirm()`.

## Installation

```bash
composer require solutionforest/filament-unsaved-changes-bar
php artisan filament:assets
```

```php
use SolutionForest\FilamentUnsavedChangesBar\UnsavedChangesBarPlugin;

$panel->plugin(UnsavedChangesBarPlugin::make());
```

Add the trait to pages that should get **Discard** (server-side form reset) and a reliable baseline:

```php
use SolutionForest\FilamentUnsavedChangesBar\Concerns\HasUnsavedChangesBar;

class EditPost extends EditRecord
{
    use HasUnsavedChangesBar;
}
```

Without the trait the bar still shows on `EditRecord` / `CreateRecord` pages. It baselines on the first rendered state, and Discard asks for confirmation and reloads the page.

## Options

```php
UnsavedChangesBarPlugin::make()
    ->position('top')                 // 'top' | 'bottom' (default)
    ->hideFormActionsWhenDirty()      // hide the page's own form action buttons while the bar is visible
    ->guardNavigation(false)          // default true: block SPA navigation and shake the bar
    ->pages([EditPost::class])        // only these pages (also enables custom pages that have a save() method)
    ->excludePages([CreateTag::class]);
```

Opt a single page out: `protected bool $hasUnsavedChangesBar = false;`

Labels are translatable (`en`, `zh_TW`, `zh_CN`).

## Custom pages

Requirements: `InteractsWithForms`, a public `$data` array bound with `->statePath('data')`, and a save method (default `save`). Use the trait and override `getUnsavedChangesBarSaveMethod()` if your method has another name. Call `$this->rememberUnsavedChangesBarData()` after each successful save. Register the page with `->pages([...])`.

## Limitations

- Only `$data` is tracked. Relation managers, table actions and modals are separate Livewire components.
- Closing the tab or a full reload shows the browser's native dialog; browsers don't allow custom UI there.
- Fields that change `data` while initialising (FileUpload, RichEditor) are re-baselined once, 500 ms after load, if the user hasn't interacted yet.
- `hideFormActionsWhenDirty()` hides every `.fi-sc-actions` inside the page's `<form>`.

## Development

```bash
composer test && composer analyse && composer format
```

A Testbench workbench panel with a `Post` resource lives in `workbench/` (`vendor/bin/testbench workbench:build && vendor/bin/testbench serve`). The JS (`resources/dist/unsaved-changes-bar.js`) is a hand-written ES module; there is no build step.

## License

MIT
