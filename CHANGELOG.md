# Changelog

## v0.2.0

- Pages with several forms: the bar's Save now validates every form first, persists through `persistUnsavedChangesBar(array $states)` in one DB transaction and refreshes the baseline. Replaces calling `rememberUnsavedChangesBarData()` from your own save method on those pages.

## v0.1.0

First release. Filament 5 (Livewire 4), PHP 8.2+.

- Floating "Unsaved changes · Discard · Save" bar, same dirty check as Filament's own unsaved-changes alert
- Leave modal (Save & leave / Discard & leave / Keep editing) for link clicks, with or without SPA mode, and for the back/forward buttons; `NavigationGuard::Shake` as an alternative
- Ctrl/⌘+S saves; Discard modal on pages without the trait
- `HasUnsavedChangesBar` trait: server-side baseline and Discard, several forms via `getUnsavedChangesBarStatePaths()`
- Options: `position`, `saveButtonColor`, `discardButtonColor`, `navigationGuard`, `hideFormActionsWhenDirty`, `includePages` / `excludePages`
- Multi-panel, Octane, dark mode, mobile layout; translations `en`, `zh_TW`, `zh_CN`
