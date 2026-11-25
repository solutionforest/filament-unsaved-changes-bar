// Alpine component for the unsaved-changes bar. Hand-written ES module (no build step needed).
// The dirty check mirrors Filament's own unsaved-changes alert:
//   md5(JSON.stringify($wire.data).replace(/\\/g, ''))  vs  the server-side baseline hash.

export default function unsavedChangesBar({
    saveMethod = 'save',
    hasTrait = false,
    hideFormActions = false,
    navigationGuard = 'modal',
    modalId = null,
    discardConfirm = 'Discard all unsaved changes?',
} = {}) {
    return {
        isDirty: false,
        isSaving: false,
        shaking: false,

        // Client-side baselines (see settle()).
        localBaseline: null,
        override: null,
        overrideFor: null,
        userInteracted: false,
        bypassGuard: false,
        pendingUrl: null,
        cleanups: [],
        timers: {},

        init() {
            const root = this.$wire.$el ?? document
            const markInteracted = (event) => {
                if (event.isTrusted) this.userInteracted = true
            }

            for (const type of ['keydown', 'pointerdown', 'input']) {
                root.addEventListener?.(type, markInteracted, true)
                this.cleanups.push(() => root.removeEventListener?.(type, markInteracted, true))
            }

            const check = () => this.check()
            this.$wire.$watch('data', () => this.debounce('check', check, 150))
            this.$wire.$watch('unsavedChangesBarHash', check)
            this.$wire.$watch('savedDataHash', check)

            // FileUpload / RichEditor and friends mutate `data` while initialising, so the server-side hash taken on
            // mount can differ from the first rendered state. Re-baseline once, after the first render settles,
            // as long as the user hasn't touched anything yet.
            this.debounce('settle', () => this.settle(), 500)

            if (navigationGuard !== 'none') {
                // Capture phase on window: runs before Filament's own `confirm()` handler, which we then pre-empt
                // so the user never gets double-prompted.
                const onNavigate = (event) => {
                    if (this.bypassGuard || this.hasRedirect()) return

                    // The watcher is debounced: re-check now so a quick type-then-click is still caught.
                    this.check()
                    if (!this.isDirty) return

                    event.preventDefault()
                    event.stopImmediatePropagation()

                    if (navigationGuard === 'modal' && event.detail?.url) {
                        this.pendingUrl = event.detail.url.toString()
                        this.toggleModal(true)
                    } else {
                        this.shake()
                    }
                }
                window.addEventListener('livewire:navigate', onNavigate, true)
                this.cleanups.push(() => window.removeEventListener('livewire:navigate', onNavigate, true))
            }

            // Tab close / reload: browsers only allow their native dialog.
            const onBeforeUnload = (event) => {
                if (this.bypassGuard || this.hasRedirect()) return

                this.check()
                if (!this.isDirty) return

                event.preventDefault()
                event.returnValue = true
            }
            window.addEventListener('beforeunload', onBeforeUnload)
            this.cleanups.push(() => window.removeEventListener('beforeunload', onBeforeUnload))

            this.$wire.__instance?.addCleanup?.(() => this.destroy())
        },

        destroy() {
            Object.values(this.timers).forEach(clearTimeout)
            this.cleanups.splice(0).forEach((cleanup) => cleanup())
            document.body.classList.remove('fi-ucb-hide-actions')
        },

        debounce(key, callback, wait) {
            clearTimeout(this.timers[key])
            this.timers[key] = setTimeout(callback, wait)
        },

        hasRedirect() {
            return Boolean(this.$wire.__instance?.effects?.redirect)
        },

        currentHash() {
            const serialised = JSON.stringify(this.$wire.data ?? {}).replace(/\\/g, '')

            return typeof window.jsMd5 === 'function' ? window.jsMd5(serialised) : serialised
        },

        serverHash() {
            return this.$wire.unsavedChangesBarHash || this.$wire.savedDataHash || null
        },

        baseline() {
            const server = this.serverHash()

            if (server === null) return this.localBaseline

            // A client-side re-baseline only applies while the server hash it was taken against is still current.
            return this.override !== null && this.overrideFor === server ? this.override : server
        },

        settle() {
            const current = this.currentHash()
            const server = this.serverHash()

            if (server === null) {
                // Page without any server-side hash: baseline on the first settled state.
                if (this.localBaseline === null) this.localBaseline = current
            } else if (!this.userInteracted && current !== server) {
                this.override = current
                this.overrideFor = server
            }

            this.check()
        },

        check() {
            const baseline = this.baseline()
            this.isDirty = baseline !== null && !this.hasRedirect() && this.currentHash() !== baseline

            document.body.classList.toggle('fi-ucb-hide-actions', hideFormActions && this.isDirty)
        },

        shake() {
            this.shaking = false
            requestAnimationFrame(() => {
                this.shaking = true
                this.$el.querySelector('.fi-ucb-btn-save')?.focus()
                setTimeout(() => (this.shaking = false), 600)
            })
        },

        toggleModal(open) {
            window.dispatchEvent(new CustomEvent(open ? 'open-modal' : 'close-modal', { detail: { id: modalId } }))
        },

        keepEditing() {
            this.pendingUrl = null
            this.toggleModal(false)
            this.$el.querySelector('.fi-ucb-btn-save')?.focus()
        },

        leave() {
            const url = this.pendingUrl
            this.pendingUrl = null
            this.bypassGuard = true
            this.toggleModal(false)

            if (url) window.Livewire.navigate(url)
        },

        leaveWithoutSaving() {
            this.leave()
        },

        async saveAndLeave() {
            await this.save()

            // A validation error keeps the form dirty: stay, close the modal so the errors are visible.
            if (this.isDirty) {
                this.pendingUrl = null
                this.toggleModal(false)
                this.shake()

                return
            }

            this.leave()
        },

        async save() {
            if (this.isSaving) return

            this.isSaving = true

            try {
                await this.$wire[saveMethod]()
                await this.$nextTick(() => {})

                // Without a server-side hash, re-baseline after a save that raised no validation error.
                if (this.serverHash() === null && !document.querySelector('[data-validation-error]')) {
                    this.localBaseline = this.currentHash()
                }
            } finally {
                this.isSaving = false
                this.check()
            }
        },

        async discard() {
            if (this.isSaving) return

            if (hasTrait) {
                this.isSaving = true

                try {
                    await this.$wire.discardChanges()
                    this.override = null
                    this.userInteracted = false
                    this.debounce('settle', () => this.settle(), 500)
                } finally {
                    this.isSaving = false
                    this.check()
                }

                return
            }

            if (confirm(discardConfirm)) {
                this.bypassGuard = true
                window.location.reload()
            }
        },
    }
}
