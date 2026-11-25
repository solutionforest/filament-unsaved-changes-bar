<?php

namespace Demo\App\Filament\Resources\PlainPostResource\Pages;

use Demo\App\Filament\Resources\PlainPostResource;
use Filament\Resources\Pages\ListRecords;

class ListPlainPosts extends ListRecords
{
    protected static string $resource = PlainPostResource::class;
}
