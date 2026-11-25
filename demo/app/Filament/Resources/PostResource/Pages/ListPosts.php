<?php

namespace Demo\App\Filament\Resources\PostResource\Pages;

use Demo\App\Filament\Resources\PostResource;
use Filament\Resources\Pages\ListRecords;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;
}
