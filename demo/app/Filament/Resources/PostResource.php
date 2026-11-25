<?php

namespace Demo\App\Filament\Resources;

use Demo\App\Filament\Resources\PostResource\Pages;
use Demo\App\Models\Post;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Exercises the field types that are known to be tricky for dirty-checking:
 * RichEditor and FileUpload (mutate state while initialising), Repeater and TagsInput (nested arrays).
 */
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Content')->columnSpan(2)->schema([
                TextInput::make('title')->required()->maxLength(120),
                TextInput::make('slug')->prefix('/blog/'),
                RichEditor::make('body'),
                Repeater::make('highlights')
                    ->schema([TextInput::make('text')->required()])
                    ->defaultItems(0)
                    ->addActionLabel('Add highlight'),
            ]),
            Section::make('Publishing')->columnSpan(1)->schema([
                Select::make('status')
                    ->options(['draft' => 'Draft', 'review' => 'In review', 'published' => 'Published'])
                    ->default('draft')
                    ->required(),
                Toggle::make('is_featured')->label('Featured'),
                DatePicker::make('published_at'),
                TagsInput::make('tags'),
                FileUpload::make('cover')->image()->disk('public')->directory('covers'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable(),
            TextColumn::make('status')->badge(),
            IconColumn::make('is_featured')->boolean()->label('Featured'),
            TextColumn::make('updated_at')->since(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
