<?php

namespace Demo\App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Schema;
use SolutionForest\FilamentUnsavedChangesBar\Concerns\HasUnsavedChangesBar;

/**
 * A page with TWO forms and two state paths. The bar tracks both and one Save persists both.
 *
 * @property-read Schema $profileForm
 * @property-read Schema $notificationsForm
 */
class Preferences extends Page implements HasForms
{
    use HasUnsavedChangesBar;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $title = 'Preferences (two forms)';

    /** @var array<string, mixed>|null */
    public ?array $profileData = [];

    /** @var array<string, mixed>|null */
    public ?array $notificationsData = [];

    public static function getUnsavedChangesBarStatePaths(): array
    {
        return ['profileData', 'notificationsData'];
    }

    public function mount(): void
    {
        $this->fillUnsavedChangesBarForms();
    }

    protected function fillUnsavedChangesBarForms(): void
    {
        $this->profileForm->fill(cache('demo.preferences.profile', ['name' => 'Demo user', 'timezone' => 'Asia/Hong_Kong']));
        $this->notificationsForm->fill(cache('demo.preferences.notifications', ['email_digest' => true]));
    }

    public function profileForm(Schema $schema): Schema
    {
        return $schema->statePath('profileData')->components([
            TextInput::make('name')->required(),
            Select::make('timezone')->options(['Asia/Hong_Kong' => 'Hong Kong', 'Asia/Taipei' => 'Taipei', 'UTC' => 'UTC'])->required(),
        ]);
    }

    public function notificationsForm(Schema $schema): Schema
    {
        return $schema->statePath('notificationsData')->components([
            Toggle::make('email_digest')->label('Weekly email digest'),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('profileForm'),
            EmbeddedSchema::make('notificationsForm'),
            Actions::make([Action::make('save')->action('save')]),
        ]);
    }

    public function save(): void
    {
        cache()->forever('demo.preferences.profile', $this->profileForm->getState());
        cache()->forever('demo.preferences.notifications', $this->notificationsForm->getState());

        $this->rememberUnsavedChangesBarData();

        Notification::make()->success()->title('Preferences saved')->send();
    }
}
