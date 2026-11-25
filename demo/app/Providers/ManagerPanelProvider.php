<?php

namespace Demo\App\Providers;

use Demo\App\Filament\Resources\PostResource;
use Demo\App\Filament\Resources\PostResource\Pages\EditPost;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use SolutionForest\FilamentUnsavedChangesBar\Enums\BarPosition;
use SolutionForest\FilamentUnsavedChangesBar\Enums\NavigationGuard;
use SolutionForest\FilamentUnsavedChangesBar\UnsavedChangesBarPlugin;

class ManagerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('manager')
            ->path('manager')
            ->login()
            ->brandName('Manager panel (2nd panel)')
            ->colors(['primary' => Color::Indigo])
            ->spa()
            ->pages([Dashboard::class])
            ->resources([PostResource::class])
            ->plugin(
                // A second panel with its own configuration: the two panels don't affect each other.
                UnsavedChangesBarPlugin::make()
                    ->position(BarPosition::Top)
                    ->saveButtonColor('success')
                    ->discardButtonColor('danger')
                    ->navigationGuard(NavigationGuard::Shake)
                    ->includePages([EditPost::class]),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
