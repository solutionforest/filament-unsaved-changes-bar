<?php

namespace SolutionForest\FilamentUnsavedChangesBar\Concerns;

use BadMethodCallException;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    /**
     * Pages with several tracked forms save through saveUnsavedChangesBar(); single-form pages keep save() / create().
     */
    public static function getUnsavedChangesBarSaveMethod(): string
    {
        if (count(static::getUnsavedChangesBarStatePaths()) > 1) {
            return 'saveUnsavedChangesBar';
        }

        return is_a(static::class, CreateRecord::class, true) ? 'create' : 'save';
    }

    /**
     * Save for pages with several forms: validate every form first (so one failure saves nothing and all errors
     * show), then persist everything in one transaction, then refresh the baseline.
     */
    public function saveUnsavedChangesBar(): void
    {
        $failure = null;

        foreach (static::getUnsavedChangesBarStatePaths() as $path) {
            try {
                $this->getUnsavedChangesBarForm($path)->validate();
            } catch (ValidationException $exception) {
                $failure = $exception;
            }
        }

        if ($failure !== null) {
            throw $failure;
        }

        $states = [];

        foreach (static::getUnsavedChangesBarStatePaths() as $path) {
            $states[$path] = $this->getUnsavedChangesBarForm($path)->getState();
        }

        DB::transaction(fn () => $this->persistUnsavedChangesBar($states));

        $this->rememberUnsavedChangesBarData();
    }

    /**
     * Write the validated state of every tracked form. Implement this on pages with several forms.
     *
     * @param  array<string, array<string, mixed>>  $states  keyed by state path, e.g. ['profileData' => [...]]
     */
    protected function persistUnsavedChangesBar(array $states): void
    {
        throw new BadMethodCallException(static::class.' tracks several forms and must implement persistUnsavedChangesBar().');
    }

    /**
     * Form name for a state path: `profileData` → `profileForm`, `data` → `form`. Override for other conventions.
     */
    protected function getUnsavedChangesBarFormName(string $path): string
    {
        return $path === 'data' ? 'form' : Str::beforeLast($path, 'Data').'Form';
    }

    private function getUnsavedChangesBarForm(string $path): Schema
    {
        return $this->{$this->getUnsavedChangesBarFormName($path)};
    }
}
