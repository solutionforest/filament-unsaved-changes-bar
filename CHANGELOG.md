# Changelog

## Unreleased

- Dev: [Pun](https://github.com/shipfastlabs/pun) format check and linter (`composer pun`), also run in CI. Static analysis stays on Larastan.

## v0.2.2

- Static analysis raised to PHPStan level 10 (max). Misconfigured pages now fail with a clear `LogicException` (e.g. `getUnsavedChangesBarStatePaths()` returning a non-list, or a state path without a matching form) instead of a PHP type error.
- README: PHPStan, Pint, Pest and Playwright badges.

## v0.2.1

- Requires PHP 8.3+ (the Filament 5 / Livewire 4 dependency set doesn't install on PHP 8.2).
- Dropped the unused `pestphp/pest-plugin-livewire` dev dependency.
- Full MIT license text, so GitHub and Packagist detect the license.
- CI runs on GitHub-hosted runners.
- Docs: cover and social preview images, badges, and a comparison with Filament's `->unsavedChangesAlerts()`.

## v0.2.0

- Pages with several forms: the bar's Save now validates every form first, persists through `persistUnsavedChangesBar(array $states)` in one DB transaction and refreshes the baseline. Replaces calling `rememberUnsavedChangesBarData()` from your own save method on those pages.

## v0.1.0

First release. Filament 5 (Livewire 4), PHP 8.3+.

- Floating "Unsaved changes · Discard · Save" bar, same dirty check as Filament's own unsaved-changes alert
- Leave modal (Save & leave / Discard & leave / Keep editing) for link clicks, with or without SPA mode, and for the back/forward buttons; `NavigationGuard::Shake` as an alternative
- Ctrl/⌘+S saves; Discard modal on pages without the trait
- `HasUnsavedChangesBar` trait: server-side baseline and Discard, several forms via `getUnsavedChangesBarStatePaths()`
- Options: `position`, `saveButtonColor`, `discardButtonColor`, `navigationGuard`, `hideFormActionsWhenDirty`, `includePages` / `excludePages`
- Multi-panel, Octane, dark mode, mobile layout; translations `en`, `zh_TW`, `zh_CN`
