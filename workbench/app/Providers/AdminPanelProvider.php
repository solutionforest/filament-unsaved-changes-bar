<?php

namespace Workbench\App\Providers;

use Filament\Panel;
use Filament\PanelProvider;
use SolutionForest\FilamentUnsavedChangesBar\UnsavedChangesBarPlugin;
use Workbench\App\Filament\Resources\PostResource;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->resources([PostResource::class])
            ->plugin(UnsavedChangesBarPlugin::make());
    }
}
