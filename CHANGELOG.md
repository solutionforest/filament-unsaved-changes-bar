# Changelog

## v0.1.0

First release. Filament 5 (Livewire 4), PHP 8.2+.

- Floating "Unsaved changes · Discard · Save" bar, same dirty check as Filament's own unsaved-changes alert
- Leave modal (Save & leave / Discard & leave / Keep editing) for link clicks, with or without SPA mode, and for the back/forward buttons; `NavigationGuard::Shake` as an alternative
- Ctrl/⌘+S saves; Discard modal on pages without the trait
- `HasUnsavedChangesBar` trait: server-side baseline and Discard, several forms via `getUnsavedChangesBarStatePaths()`
- Options: `position`, `saveButtonColor`, `discardButtonColor`, `navigationGuard`, `hideFormActionsWhenDirty`, `includePages` / `excludePages`
- Multi-panel, Octane, dark mode, mobile layout; translations `en`, `zh_TW`, `zh_CN`
