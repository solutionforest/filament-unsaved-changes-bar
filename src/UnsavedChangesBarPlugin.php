<?php

namespace SolutionForest\FilamentUnsavedChangesBar;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\View\PanelsRenderHook;
use LogicException;
use ReflectionClass;
use SolutionForest\FilamentUnsavedChangesBar\Concerns\HasUnsavedChangesBar;
use SolutionForest\FilamentUnsavedChangesBar\Enums\BarPosition;
use SolutionForest\FilamentUnsavedChangesBar\Enums\NavigationGuard;
use SolutionForest\FilamentUnsavedChangesBar\Exceptions\ConflictingPageFilters;

class UnsavedChangesBarPlugin implements Plugin
{
    protected BarPosition $position = BarPosition::Bottom;

    protected string $saveButtonColor = 'primary';

    protected string $discardButtonColor = 'gray';

    protected bool $hideFormActionsWhenDirty = false;

    protected NavigationGuard $navigationGuard = NavigationGuard::Modal;

    /** @var array<class-string>|null */
    protected ?array $includedPages = null;

    /** @var array<class-string>|null */
    protected ?array $excludedPages = null;

    public static function make(): static
    {
        $plugin = app(static::class);

        if (! $plugin instanceof static) {
            throw new LogicException('The container did not resolve ['.static::class.'].');
        }

        return $plugin;
    }

    public static function get(): static
    {
        $plugin = filament(static::make()->getId());

        if (! $plugin instanceof static) {
            throw new LogicException('The current panel does not register ['.static::class.'].');
        }

        return $plugin;
    }

    public function getId(): string
    {
        return 'unsaved-changes-bar';
    }

    public function position(BarPosition $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Any colour registered with Filament: 'primary', 'success', 'warning', 'danger', 'info', 'gray',
     * or your own from FilamentColor::register().
     */
    public function saveButtonColor(string $color): static
    {
        $this->saveButtonColor = $color;

        return $this;
    }

    public function discardButtonColor(string $color): static
    {
        $this->discardButtonColor = $color;

        return $this;
    }

    public function hideFormActionsWhenDirty(bool $condition = true): static
    {
        $this->hideFormActionsWhenDirty = $condition;

        return $this;
    }

    public function navigationGuard(NavigationGuard $guard): static
    {
        $this->navigationGuard = $guard;

        return $this;
    }

    /**
     * Show the bar only on these pages. Also enables custom pages that have a save method.
     *
     * @param  array<class-string>  $pages
     *
     * @throws ConflictingPageFilters when excludePages() is also used
     */
    public function includePages(array $pages): static
    {
        if ($this->excludedPages !== null) {
            throw ConflictingPageFilters::make();
        }

        $this->includedPages = $pages;

        return $this;
    }

    /**
     * Show the bar everywhere it is eligible, except on these pages.
     *
     * @param  array<class-string>  $pages
     *
     * @throws ConflictingPageFilters when includePages() is also used
     */
    public function excludePages(array $pages): static
    {
        if ($this->includedPages !== null) {
            throw ConflictingPageFilters::make();
        }

        $this->excludedPages = $pages;

        return $this;
    }

    public function getPosition(): BarPosition
    {
        return $this->position;
    }

    public function getSaveButtonColor(): string
    {
        return $this->saveButtonColor;
    }

    public function getDiscardButtonColor(): string
    {
        return $this->discardButtonColor;
    }

    public function shouldHideFormActionsWhenDirty(): bool
    {
        return $this->hideFormActionsWhenDirty;
    }

    public function getNavigationGuard(): NavigationGuard
    {
        return $this->navigationGuard;
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::PAGE_END,
            /** @param  array<int, mixed>  $scopes */
            fn (array $scopes = []): string => $this->renderFor(is_string($scopes[0] ?? null) ? $scopes[0] : null),
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

        if ($this->excludedPages !== null && in_array($page, $this->excludedPages, true)) {
            return false;
        }

        if ($this->includedPages !== null && ! in_array($page, $this->includedPages, true)) {
            return false;
        }

        $defaults = (new ReflectionClass($page))->getDefaultProperties();

        if (($defaults['hasUnsavedChangesBar'] ?? true) === false) {
            return false;
        }

        foreach ($this->getStatePaths($page) as $path) {
            if (! property_exists($page, $path)) {
                return false;
            }
        }

        return is_a($page, EditRecord::class, true)
            || is_a($page, CreateRecord::class, true)
            || in_array(HasUnsavedChangesBar::class, class_uses_recursive($page), true)
            || $this->includedPages !== null && method_exists($page, 'save');
    }

    /**
     * @return array<int, string>
     */
    public function getStatePaths(string $page): array
    {
        if (! method_exists($page, 'getUnsavedChangesBarStatePaths')) {
            return ['data'];
        }

        $paths = $page::getUnsavedChangesBarStatePaths();

        if (! is_array($paths) || $paths === []) {
            throw new LogicException("{$page}::getUnsavedChangesBarStatePaths() must return a non-empty list of property names.");
        }

        $valid = [];

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                throw new LogicException("{$page}::getUnsavedChangesBarStatePaths() must only contain property names.");
            }

            $valid[] = $path;
        }

        return $valid;
    }

    public function getSaveMethod(string $page): string
    {
        if (method_exists($page, 'getUnsavedChangesBarSaveMethod')) {
            $method = $page::getUnsavedChangesBarSaveMethod();

            if (! is_string($method) || $method === '') {
                throw new LogicException("{$page}::getUnsavedChangesBarSaveMethod() must return a method name.");
            }

            return $method;
        }

        return is_a($page, CreateRecord::class, true) ? 'create' : 'save';
    }

    public function renderFor(?string $page): string
    {
        if ($page === null || ! $this->isPageEligible($page)) {
            return '';
        }

        $view = view('filament-unsaved-changes-bar::bar', [
            'position' => $this->position->value,
            'saveButtonColor' => $this->saveButtonColor,
            'discardButtonColor' => $this->discardButtonColor,
            'saveMethod' => $this->getSaveMethod($page),
            'statePaths' => $this->getStatePaths($page),
            'hasTrait' => method_exists($page, 'discardChanges') && in_array(HasUnsavedChangesBar::class, class_uses_recursive($page), true),
            'hideFormActions' => $this->hideFormActionsWhenDirty,
            'navigationGuard' => $this->navigationGuard->value,
        ]);

        return $view->render();
    }
}
