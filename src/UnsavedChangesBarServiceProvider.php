<?php

namespace SolutionForest\FilamentUnsavedChangesBar;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class UnsavedChangesBarServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-unsaved-changes-bar';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)->hasViews()->hasTranslations();
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            AlpineComponent::make('unsaved-changes-bar', __DIR__.'/../resources/dist/unsaved-changes-bar.js'),
            Css::make('unsaved-changes-bar-styles', __DIR__.'/../resources/dist/unsaved-changes-bar.css'),
        ], 'solutionforest/filament-unsaved-changes-bar');
    }
}
