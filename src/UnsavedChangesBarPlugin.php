<?php

namespace SolutionForest\FilamentUnsavedChangesBar;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\View\PanelsRenderHook;
use ReflectionClass;
use SolutionForest\FilamentUnsavedChangesBar\Concerns\HasUnsavedChangesBar;

class UnsavedChangesBarPlugin implements Plugin
{
    protected string $position = 'bottom';

    protected bool $hideFormActionsWhenDirty = false;

    protected bool $guardNavigation = true;

    /** @var array<class-string>|null */
    protected ?array $pages = null;

    /** @var array<class-string> */
    protected array $excludedPages = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'unsaved-changes-bar';
    }

    public function position(string $position): static
    {
        $this->position = $position === 'top' ? 'top' : 'bottom';

        return $this;
    }

    public function hideFormActionsWhenDirty(bool $condition = true): static
    {
        $this->hideFormActionsWhenDirty = $condition;

        return $this;
    }

    public function guardNavigation(bool $condition = true): static
    {
        $this->guardNavigation = $condition;

        return $this;
    }

    /** @param  array<class-string>  $pages */
    public function pages(array $pages): static
    {
        $this->pages = $pages;

        return $this;
    }

    /** @param  array<class-string>  $pages */
    public function excludePages(array $pages): static
    {
        $this->excludedPages = $pages;

        return $this;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function shouldHideFormActionsWhenDirty(): bool
    {
        return $this->hideFormActionsWhenDirty;
    }

    public function shouldGuardNavigation(): bool
    {
        return $this->guardNavigation;
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::PAGE_END,
            fn (array $scopes = []): string => $this->renderFor($scopes[0] ?? null),
        );
    }

    public function boot(Panel $panel): void {}

    /**
     * Whether the bar should be rendered on the given page class.
     */
    public function isPageEligible(?string $page): bool
    {
        if (! is_string($page) || ! class_exists($page)) {
            return false;
        }

        if (in_array($page, $this->excludedPages, true)) {
            return false;
        }

        if ($this->pages !== null && ! in_array($page, $this->pages, true)) {
            return false;
        }

        $defaults = (new ReflectionClass($page))->getDefaultProperties();

        if (($defaults['hasUnsavedChangesBar'] ?? true) === false) {
            return false;
        }

        if (! property_exists($page, 'data')) {
            return false;
        }

        return is_a($page, EditRecord::class, true)
            || is_a($page, CreateRecord::class, true)
            || in_array(HasUnsavedChangesBar::class, class_uses_recursive($page), true)
            || $this->pages !== null && method_exists($page, 'save');
    }

    public function getSaveMethod(string $page): string
    {
        if (method_exists($page, 'getUnsavedChangesBarSaveMethod')) {
            return $page::getUnsavedChangesBarSaveMethod();
        }

        return is_a($page, CreateRecord::class, true) ? 'create' : 'save';
    }

    public function renderFor(?string $page): string
    {
        if (! $this->isPageEligible($page)) {
            return '';
        }

        $view = view('filament-unsaved-changes-bar::bar', [
            'position' => $this->position,
            'saveMethod' => $this->getSaveMethod($page),
            'hasTrait' => method_exists($page, 'discardChanges') && in_array(HasUnsavedChangesBar::class, class_uses_recursive($page), true),
            'hideFormActions' => $this->hideFormActionsWhenDirty,
            'guardNavigation' => $this->guardNavigation,
        ]);

        return $view->render();
    }
}
