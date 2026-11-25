<?php

namespace Workbench\App\Filament\Resources\PostResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use SolutionForest\FilamentUnsavedChangesBar\Concerns\HasUnsavedChangesBar;
use Workbench\App\Filament\Resources\PostResource;

class CreatePost extends CreateRecord
{
    use HasUnsavedChangesBar;

    protected static string $resource = PostResource::class;
}
