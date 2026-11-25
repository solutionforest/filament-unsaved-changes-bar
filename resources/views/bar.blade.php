@php
    use Filament\Support\Facades\FilamentAsset;

    $modalId = 'unsaved-changes-bar-leave';
    $discardModalId = 'unsaved-changes-bar-discard';
@endphp

<div
    wire:ignore
    x-load
    x-load-src="{{ FilamentAsset::getAlpineComponentSrc('unsaved-changes-bar', 'solutionforest/filament-unsaved-changes-bar') }}"
    x-data="unsavedChangesBar({
        saveMethod: @js($saveMethod),
        statePaths: @js($statePaths),
        hasTrait: @js($hasTrait),
        hideFormActions: @js($hideFormActions),
        navigationGuard: @js($navigationGuard),
        modalId: @js($modalId),
        discardModalId: @js($discardModalId),
    })"
    class="fi-ucb-root"
    data-unsaved-changes-bar
>
    <div
        x-cloak
        x-show="isDirty"
        x-transition:enter="fi-ucb-enter"
        x-transition:enter-start="fi-ucb-enter-start"
        x-transition:leave="fi-ucb-leave"
        x-transition:leave-end="fi-ucb-enter-start"
        x-bind:class="{ 'fi-ucb-shake': shaking }"
        role="status"
        aria-live="polite"
        class="fi-ucb fi-ucb-{{ $position }}"
    >
        <span class="fi-ucb-label">
            <x-filament::icon icon="heroicon-m-exclamation-circle" class="fi-ucb-icon" />
            {{ __('filament-unsaved-changes-bar::bar.label') }}
        </span>

        <x-filament::button
            :color="$discardButtonColor"
            outlined
            size="sm"
            class="fi-ucb-btn-discard"
            x-on:click="discard()"
            x-bind:disabled="isSaving"
        >
            {{ __('filament-unsaved-changes-bar::bar.discard') }}
        </x-filament::button>

        <x-filament::button
            :color="$saveButtonColor"
            size="sm"
            class="fi-ucb-btn-save"
            x-on:click="save()"
            x-bind:disabled="isSaving"
            x-bind:aria-busy="isSaving"
        >
            <span x-show="! isSaving">{{ __('filament-unsaved-changes-bar::bar.save') }}</span>
            <span x-show="isSaving" x-cloak>{{ __('filament-unsaved-changes-bar::bar.saving') }}</span>
        </x-filament::button>
    </div>

    @if ($navigationGuard === 'modal')
        {{-- Shown when an SPA link is clicked while the form is dirty. Lives in the bar's Alpine scope. --}}
        <x-filament::modal
            :id="$modalId"
            icon="heroicon-o-exclamation-triangle"
            icon-color="warning"
            :heading="__('filament-unsaved-changes-bar::bar.leave_heading')"
            :description="__('filament-unsaved-changes-bar::bar.leave_description')"
            width="md"
            :close-by-clicking-away="false"
            footer-actions-alignment="end"
        >
            <x-slot name="footerActions">
                <x-filament::button color="gray" outlined x-on:click="keepEditing()" x-bind:disabled="isSaving" class="fi-ucb-modal-keep">
                    {{ __('filament-unsaved-changes-bar::bar.keep_editing') }}
                </x-filament::button>

                <x-filament::button :color="$discardButtonColor === 'gray' ? 'danger' : $discardButtonColor" outlined x-on:click="leave()" x-bind:disabled="isSaving" class="fi-ucb-modal-discard">
                    {{ __('filament-unsaved-changes-bar::bar.discard_and_leave') }}
                </x-filament::button>

                <x-filament::button :color="$saveButtonColor" x-on:click="saveAndLeave()" x-bind:disabled="isSaving" class="fi-ucb-modal-save">
                    <span x-show="! isSaving">{{ __('filament-unsaved-changes-bar::bar.save_and_leave') }}</span>
                    <span x-show="isSaving" x-cloak>{{ __('filament-unsaved-changes-bar::bar.saving') }}</span>
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endif

    @unless ($hasTrait)
        {{-- Pages without the trait can't reset server-side: confirm, then reload. --}}
        <x-filament::modal
            :id="$discardModalId"
            icon="heroicon-o-trash"
            icon-color="danger"
            :heading="__('filament-unsaved-changes-bar::bar.discard_heading')"
            :description="__('filament-unsaved-changes-bar::bar.discard_description')"
            width="md"
            footer-actions-alignment="end"
        >
            <x-slot name="footerActions">
                <x-filament::button color="gray" outlined x-on:click="cancelDiscard()" class="fi-ucb-modal-keep">
                    {{ __('filament-unsaved-changes-bar::bar.cancel') }}
                </x-filament::button>

                <x-filament::button color="danger" x-on:click="confirmDiscardReload()" class="fi-ucb-modal-discard-confirm">
                    {{ __('filament-unsaved-changes-bar::bar.discard') }}
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endunless
</div>
