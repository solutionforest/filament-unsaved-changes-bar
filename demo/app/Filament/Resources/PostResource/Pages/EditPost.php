<?php

namespace Demo\App\Filament\Resources\PostResource\Pages;

use Demo\App\Filament\Resources\PostResource;
use Filament\Resources\Pages\EditRecord;
use SolutionForest\FilamentUnsavedChangesBar\Concerns\HasUnsavedChangesBar;

class EditPost extends EditRecord
{
    use HasUnsavedChangesBar;

    protected static string $resource = PostResource::class;
}
