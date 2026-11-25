<?php

namespace Workbench\App\Filament\Resources\PostResource\Pages;

use Filament\Resources\Pages\EditRecord;
use SolutionForest\FilamentUnsavedChangesBar\Concerns\HasUnsavedChangesBar;
use Workbench\App\Filament\Resources\PostResource;

class EditPost extends EditRecord
{
    use HasUnsavedChangesBar;

    protected static string $resource = PostResource::class;
}
