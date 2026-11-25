@php
    use Filament\Support\Facades\FilamentAsset;
@endphp

<div
    wire:ignore
    x-load
    x-load-src="{{ FilamentAsset::getAlpineComponentSrc('unsaved-changes-bar', 'solutionforest/filament-unsaved-changes-bar') }}"
    x-data="unsavedChangesBar({
        saveMethod: @js($saveMethod),
        hasTrait: @js($hasTrait),
        hideFormActions: @js($hideFormActions),
        guardNavigation: @js($guardNavigation),
        discardConfirm: @js(__('filament-unsaved-changes-bar::bar.discard_confirm')),
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
        :class="{ 'fi-ucb-shake': shaking }"
        role="status"
        aria-live="polite"
        class="fi-ucb fi-ucb-{{ $position }}"
    >
        <span class="fi-ucb-label">{{ __('filament-unsaved-changes-bar::bar.label') }}</span>

        <button type="button" class="fi-ucb-btn fi-ucb-btn-discard" x-on:click="discard()" x-bind:disabled="isSaving">
            {{ __('filament-unsaved-changes-bar::bar.discard') }}
        </button>

        <button type="button" class="fi-ucb-btn fi-ucb-btn-save" x-on:click="save()" x-bind:disabled="isSaving" x-bind:aria-busy="isSaving">
            <span x-show="! isSaving">{{ __('filament-unsaved-changes-bar::bar.save') }}</span>
            <span x-show="isSaving" x-cloak>{{ __('filament-unsaved-changes-bar::bar.saving') }}</span>
        </button>
    </div>
</div>
