<?php

use Demo\App\Filament\Pages\StoreSettings;
use Demo\App\Filament\Resources\PostResource\Pages\CreatePost;
use Demo\App\Filament\Resources\PostResource\Pages\EditPost;
use Demo\App\Filament\Resources\PostResource\Pages\ListPosts;
use Demo\App\Models\Post;
use Demo\App\Models\StoreSetting;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use SolutionForest\FilamentUnsavedChangesBar\UnsavedChangesBarPlugin;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Filament::getCurrentPanel()->boot();
    $this->actingAs(new class extends User
    {
        public function canAccessPanel($panel): bool
        {
            return true;
        }
    });
    $this->post = Post::create(['title' => 'Hello', 'body' => '<p>World</p>']);
});

function expectedHash(array $data): string
{
    return md5(str_replace('\\', '', json_encode($data, JSON_UNESCAPED_UNICODE)));
}

it('sets the baseline hash on mount, without panel-level unsavedChangesAlerts()', function () {
    expect(Filament::hasUnsavedChangesAlerts())->toBeFalse();

    $component = Livewire::test(EditPost::class, ['record' => $this->post->getKey()]);

    expect($component->get('unsavedChangesBarHash'))->toBe(expectedHash($component->get('data')));
});

it('refreshes the hash after a save', function () {
    $component = Livewire::test(EditPost::class, ['record' => $this->post->getKey()]);
    $before = $component->get('unsavedChangesBarHash');

    $component->fillForm(['title' => 'Changed'])->call('save')->assertHasNoFormErrors();

    expect($component->get('unsavedChangesBarHash'))
        ->not->toBe($before)
        ->toBe(expectedHash($component->get('data')));
    expect($this->post->fresh()->title)->toBe('Changed');
});

it('keeps the old hash when validation fails', function () {
    $component = Livewire::test(EditPost::class, ['record' => $this->post->getKey()]);
    $before = $component->get('unsavedChangesBarHash');

    $component->fillForm(['title' => ''])->call('save')->assertHasFormErrors(['title' => 'required']);

    expect($component->get('unsavedChangesBarHash'))->toBe($before);
});

it('discards changes on an edit page', function () {
    $component = Livewire::test(EditPost::class, ['record' => $this->post->getKey()])
        ->fillForm(['title' => 'Dirty']);

    expect($component->get('unsavedChangesBarHash'))->not->toBe(expectedHash($component->get('data')));

    $component->call('discardChanges')->assertFormSet(['title' => 'Hello']);

    expect($component->get('unsavedChangesBarHash'))->toBe(expectedHash($component->get('data')));
    expect($this->post->fresh()->title)->toBe('Hello');
});

it('discards changes on a create page', function () {
    $component = Livewire::test(CreatePost::class);
    $baseline = $component->get('unsavedChangesBarHash');

    $component->fillForm(['title' => 'Typing…'])->call('discardChanges')->assertFormSet(['title' => null]);

    expect($component->get('unsavedChangesBarHash'))->toBe($baseline)->not->toBe('');
});

it('uses the right save method per page type', function () {
    $plugin = UnsavedChangesBarPlugin::make();

    expect($plugin->getSaveMethod(EditPost::class))->toBe('save')
        ->and($plugin->getSaveMethod(CreatePost::class))->toBe('create');
});

it('renders the bar on edit and create pages only', function () {
    $edit = Livewire::test(EditPost::class, ['record' => $this->post->getKey()])->html();
    $create = Livewire::test(CreatePost::class)->html();
    $list = Livewire::test(ListPosts::class)->html();

    expect($edit)->toContain('data-unsaved-changes-bar')->toContain('Unsaved changes')->toContain('Discard')->toContain('Save')
        ->and($create)->toContain('data-unsaved-changes-bar')
        ->and($list)->not->toContain('data-unsaved-changes-bar');
});

it('respects excludePages and pages', function () {
    $plugin = UnsavedChangesBarPlugin::make();

    expect($plugin->isPageEligible(EditPost::class))->toBeTrue()
        ->and($plugin->isPageEligible(ListRecords::class))->toBeFalse()
        ->and($plugin->isPageEligible('Nope\\Missing'))->toBeFalse();

    $excluding = (new UnsavedChangesBarPlugin)->excludePages([EditPost::class]);
    expect($excluding->isPageEligible(EditPost::class))->toBeFalse()
        ->and($excluding->isPageEligible(CreatePost::class))->toBeTrue();

    $only = (new UnsavedChangesBarPlugin)->pages([CreatePost::class]);
    expect($only->isPageEligible(EditPost::class))->toBeFalse()
        ->and($only->isPageEligible(CreatePost::class))->toBeTrue();
});

it('respects the per-page opt-out property', function () {
    $page = new class extends EditPost
    {
        protected bool $hasUnsavedChangesBar = false;
    };

    expect((new UnsavedChangesBarPlugin)->isPageEligible($page::class))->toBeFalse();
});

it('renders position, options and translations', function () {
    $plugin = (new UnsavedChangesBarPlugin)->position('top')->hideFormActionsWhenDirty()->guardNavigation(false);

    expect($plugin->getPosition())->toBe('top')
        ->and($plugin->shouldHideFormActionsWhenDirty())->toBeTrue()
        ->and($plugin->shouldGuardNavigation())->toBeFalse();

    foreach (['en' => 'Unsaved changes', 'zh_TW' => '尚有未儲存的變更', 'zh_CN' => '有未保存的更改'] as $locale => $label) {
        expect(__('filament-unsaved-changes-bar::bar.label', [], $locale))->toBe($label);
    }
});

it('renders the configured position into the bar markup', function () {
    $html = (new UnsavedChangesBarPlugin)->position('top')->hideFormActionsWhenDirty()->guardNavigation(false)
        ->renderFor(EditPost::class);

    expect($html)->toContain('fi-ucb-top')->toContain('hideFormActions: true')->toContain('guardNavigation: false')
        ->and((new UnsavedChangesBarPlugin)->renderFor(EditPost::class))->toContain('fi-ucb-bottom')
        ->and((new UnsavedChangesBarPlugin)->renderFor(ListPosts::class))->toBe('');
});

it('discards changes on a custom page by calling its fillForm()', function () {
    StoreSetting::current();

    $component = Livewire::test(StoreSettings::class)
        ->assertFormSet(['store_name' => 'Forest Coffee Co.'])
        ->fillForm(['store_name' => 'Renamed'])
        ->call('discardChanges')
        ->assertFormSet(['store_name' => 'Forest Coffee Co.']);

    expect($component->get('unsavedChangesBarHash'))->toBe(expectedHash($component->get('data')));
});

it('renders the bar on a custom page that uses the trait', function () {
    expect(UnsavedChangesBarPlugin::make()->isPageEligible(StoreSettings::class))->toBeTrue()
        ->and(Livewire::test(StoreSettings::class)->html())->toContain('data-unsaved-changes-bar');
});

it('refreshes the hash after saving a custom page', function () {
    $component = Livewire::test(StoreSettings::class)
        ->fillForm(['store_name' => 'Saved name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($component->get('unsavedChangesBarHash'))->toBe(expectedHash($component->get('data')))
        ->and(StoreSetting::current()->store_name)->toBe('Saved name');
});
