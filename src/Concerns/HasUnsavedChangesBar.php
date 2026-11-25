<?php

namespace SolutionForest\FilamentUnsavedChangesBar\Concerns;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
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
            parent::rememberData();
        }

        $this->rememberUnsavedChangesBarData();
    }

    protected function rememberUnsavedChangesBarData(): void
    {
        $this->unsavedChangesBarHash = md5(str_replace('\\', '', (string) json_encode($this->data, JSON_UNESCAPED_UNICODE)));
    }

    public function discardChanges(): void
    {
        if ($this instanceof EditRecord || $this instanceof CreateRecord) {
            $this->fillForm();
        } else {
            $this->form->fill();
        }

        $this->resetValidation();
        $this->rememberUnsavedChangesBarData();
    }

    public static function getUnsavedChangesBarSaveMethod(): string
    {
        return is_a(static::class, CreateRecord::class, true) ? 'create' : 'save';
    }
}
