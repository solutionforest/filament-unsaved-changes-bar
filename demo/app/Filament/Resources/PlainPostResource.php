<?php

namespace Demo\App\Filament\Resources;

use Demo\App\Filament\Resources\PlainPostResource\Pages\EditPlainPost;
use Demo\App\Filament\Resources\PlainPostResource\Pages\ListPlainPosts;

/**
 * Same form as PostResource, but its Edit page does NOT use the HasUnsavedChangesBar trait,
 * to show the plugin's fallback behaviour (Discard confirms in a modal, then reloads).
 */
class PlainPostResource extends PostResource
{
    protected static ?string $slug = 'plain-posts';

    protected static ?string $navigationLabel = 'Posts (no trait)';

    public static function getPages(): array
    {
        return [
            'index' => ListPlainPosts::route('/'),
            'edit' => EditPlainPost::route('/{record}/edit'),
        ];
    }
}
