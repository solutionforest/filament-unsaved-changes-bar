<?php

namespace Demo\App\Filament\Resources\PostResource\Pages;

use Demo\App\Filament\Resources\PostResource;
use Filament\Resources\Pages\CreateRecord;
use SolutionForest\FilamentUnsavedChangesBar\Concerns\HasUnsavedChangesBar;

class CreatePost extends CreateRecord
{
    use HasUnsavedChangesBar;

    protected static string $resource = PostResource::class;
}
