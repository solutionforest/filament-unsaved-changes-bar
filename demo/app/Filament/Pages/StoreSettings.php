<?php

namespace Demo\App\Filament\Pages;

use Demo\App\Models\StoreSetting;
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
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use SolutionForest\FilamentUnsavedChangesBar\Concerns\HasUnsavedChangesBar;

/**
 * A custom (non-resource) page: InteractsWithForms + `$data` state path + save() + the trait.
 *
 * @property-read Schema $form
 */
class StoreSettings extends Page implements HasForms
{
    use HasUnsavedChangesBar;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $title = 'Store settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->fillForm();
    }

    public function fillForm(): void
    {
        $this->form->fill(StoreSetting::current()->only(['store_name', 'support_email', 'currency', 'maintenance_mode']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('General')->columns(2)->schema([
                    TextInput::make('store_name')->required(),
                    TextInput::make('support_email')->email()->required(),
                    Select::make('currency')->options(['HKD' => 'HKD', 'USD' => 'USD', 'TWD' => 'TWD', 'CNY' => 'CNY'])->required(),
                    Toggle::make('maintenance_mode')->inline(false),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->submit('save')]),
                ]),
        ]);
    }

    public function save(): void
    {
        StoreSetting::current()->update($this->form->getState());

        $this->rememberUnsavedChangesBarData();

        Notification::make()->success()->title('Settings saved')->send();
    }
}
