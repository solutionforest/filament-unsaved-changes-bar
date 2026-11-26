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

    /**
     * @return non-empty-array<int, string>
     */
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
        $this->profileForm->fill($this->stored('demo.preferences.profile', ['name' => 'Demo user', 'timezone' => 'Asia/Hong_Kong']));
        $this->notificationsForm->fill($this->stored('demo.preferences.notifications', ['email_digest' => true]));
    }

    /**
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    private function stored(string $key, array $default): array
    {
        $value = cache($key);

        if (! is_array($value)) {
            return $default;
        }

        $state = [];

        foreach ($value as $field => $fieldValue) {
            if (is_string($field)) {
                $state[$field] = $fieldValue;
            }
        }

        return $state;
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
            Actions::make([Action::make('save')->action('saveUnsavedChangesBar')]),
        ]);
    }

    /**
     * The bar's Save validates both forms first, then calls this inside a transaction.
     *
     * @param  array<string, array<string, mixed>>  $states
     */
    protected function persistUnsavedChangesBar(array $states): void
    {
        cache()->forever('demo.preferences.profile', $states['profileData']);
        cache()->forever('demo.preferences.notifications', $states['notificationsData']);

        Notification::make()->success()->title('Preferences saved')->send();
    }
}
