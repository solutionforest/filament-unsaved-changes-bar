<?php

namespace SolutionForest\FilamentUnsavedChangesBar\Concerns;

use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Locked;

/**
 * Optional trait for pages shown with the unsaved-changes bar.
 *
 * On Filament's EditRecord / CreateRecord pages it keeps a baseline hash of the form data
 * (computed exactly like Filament's own unsaved-changes alert, but regardless of whether the
 * panel enables `unsavedChangesAlerts()`), and provides a server-side `discardChanges()`.
 *
 * Opt a page out with `protected bool $hasUnsavedChangesBar = false;`.
 */
trait HasUnsavedChangesBar
{
    #[Locked]
    public string $unsavedChangesBarHash = '';

    public function mountHasUnsavedChangesBar(): void
    {
        $this->rememberUnsavedChangesBarData();
    }

    /**
     * Filament calls rememberData() after every successful save. Piggy-back on it so the baseline refreshes.
     */
    protected function rememberData(): void
    {
        $parent = get_parent_class($this);

        if ($parent !== false && method_exists($parent, 'rememberData')) {
            parent::rememberData(); // @phpstan-ignore staticMethod.notFound (guarded above; the trait is also used on custom pages)
        }

        $this->rememberUnsavedChangesBarData();
    }

    protected function rememberUnsavedChangesBarData(): void
    {
        $paths = static::getUnsavedChangesBarStatePaths();

        // One form: exactly Filament's own hash. Several forms: the same formula over {path: value}.
        $state = count($paths) === 1
            ? $this->{$paths[0]}
            : collect($paths)->mapWithKeys(fn (string $path): array => [$path => $this->{$path}])->all();

        $this->unsavedChangesBarHash = md5(str_replace('\\', '', (string) json_encode($state, JSON_UNESCAPED_UNICODE)));
    }

    /**
     * Livewire properties holding the form state that the bar tracks. Override on pages with several forms,
     * e.g. `['profileData', 'passwordData']`, and override fillUnsavedChangesBarForms() to reset them all.
     *
     * @return non-empty-array<int, string>
     */
    public static function getUnsavedChangesBarStatePaths(): array
    {
        return ['data'];
    }

    public function discardChanges(): void
    {
        $this->fillUnsavedChangesBarForms();

        $this->resetValidation();
        $this->rememberUnsavedChangesBarData();
    }

    /**
     * Reset the tracked forms to their saved values. Override on pages with several forms.
     */
    protected function fillUnsavedChangesBarForms(): void
    {
        // EditRecord / CreateRecord refill from the record (or defaults). Custom pages can define their own
        // fillForm() to reload saved values; otherwise the form is reset to its defaults.
        if (method_exists($this, 'fillForm')) { // @phpstan-ignore function.alreadyNarrowedType (always true on Edit/Create, not on custom pages)
            $this->fillForm();
        } else {
            $this->form->fill();
        }
    }

    public static function getUnsavedChangesBarSaveMethod(): string
    {
        return is_a(static::class, CreateRecord::class, true) ? 'create' : 'save';
    }
}
