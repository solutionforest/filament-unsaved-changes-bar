// Alpine component for the unsaved-changes bar. Hand-written ES module (no build step needed).
// The dirty check mirrors Filament's own unsaved-changes alert:
//   md5(JSON.stringify($wire.data).replace(/\\/g, ''))  vs  the server-side baseline hash.
// Pages with several forms track every state path (the hash is then taken over {path: value, ...}).

export default function unsavedChangesBar({
    saveMethod = 'save',
    hasTrait = false,
    hideFormActions = false,
    navigationGuard = 'modal',
    modalId = null,
    statePaths = ['data'],
    discardModalId = null,
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
        pendingSpa: false,
        restoring: false,
        guardUrl: null,
        guardState: null,
        guardIndex: null,
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
            statePaths.forEach((path) => this.$wire.$watch(path, () => this.debounce('check', check, 150)))
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
                    // The popstate caused by our own history.go() below: swallow it.
                    if (this.restoring) {
                        this.restoring = false
                        event.preventDefault()
                        event.stopImmediatePropagation()

                        return
                    }

                    if (this.bypassGuard || this.hasRedirect()) return

                    // The watcher is debounced: re-check now so a quick type-then-click is still caught.
                    this.check()
                    if (!this.isDirty) return

                    event.preventDefault()
                    event.stopImmediatePropagation()

                    // Back / forward button: the address bar already shows the destination. Put the current entry
                    // back so "Keep editing" really keeps editing, then ask.
                    if (event.detail?.history && this.guardUrl) this.restoreHistoryEntry()

                    this.requestLeave(event.detail?.url?.toString() ?? null, true)
                }
                window.addEventListener('livewire:navigate', onNavigate, true)
                this.cleanups.push(() => window.removeEventListener('livewire:navigate', onNavigate, true))
            }

            // Plain links (non-SPA panels do a full page load, so `livewire:navigate` never fires): intercept the click
            // itself and show the same modal / shake. Links with `wire:navigate` are handled here too.
            const onClick = (event) => {
                if (this.bypassGuard || event.defaultPrevented || event.button !== 0) return
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return

                const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null

                if (!anchor || !this.isGuardedLink(anchor)) return

                this.check()
                if (!this.isDirty || this.hasRedirect()) return

                event.preventDefault()
                event.stopImmediatePropagation()
                this.requestLeave(anchor.href, anchor.hasAttribute('wire:navigate') || [...anchor.attributes].some((a) => a.name.startsWith('wire:navigate')))
            }
            if (navigationGuard !== 'none') {
                document.addEventListener('click', onClick, true)
                this.cleanups.push(() => document.removeEventListener('click', onClick, true))
            }

            // Ctrl/Cmd+S saves while the bar is showing (and stops the browser's "save page" dialog).
            const onKeydown = (event) => {
                if (!(event.ctrlKey || event.metaKey) || event.altKey || event.key.toLowerCase() !== 's') return

                this.check()
                if (!this.isDirty) return

                event.preventDefault()
                this.save()
            }
            document.addEventListener('keydown', onKeydown)
            this.cleanups.push(() => document.removeEventListener('keydown', onKeydown))

            const onResize = () => this.syncLayout()
            window.addEventListener('resize', onResize)
            this.cleanups.push(() => window.removeEventListener('resize', onResize))

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
            document.body.classList.remove('fi-ucb-hide-actions', 'fi-ucb-visible')
            document.documentElement.style.removeProperty('--fi-ucb-left')
        },

        debounce(key, callback, wait) {
            clearTimeout(this.timers[key])
            this.timers[key] = setTimeout(callback, wait)
        },

        hasRedirect() {
            return Boolean(this.$wire.__instance?.effects?.redirect)
        },

        currentHash() {
            const state = statePaths.length === 1
                ? (this.$wire[statePaths[0]] ?? {})
                : Object.fromEntries(statePaths.map((path) => [path, this.$wire[path] ?? null]))
            const serialised = JSON.stringify(state).replace(/\\/g, '')

            return typeof window.jsMd5 === 'function' ? window.jsMd5(serialised) : serialised
        },

        serverHash() {
            // Livewire's $wire returns a *function* for unknown properties, so only accept real, non-empty strings.
            for (const name of ['unsavedChangesBarHash', 'savedDataHash']) {
                const value = this.$wire[name]

                if (typeof value === 'string' && value !== '') return value
            }

            return null
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

            // While clean, remember where this page lives (history entry + URL) so a back-button press can be undone.
            // Not taken at init: on an SPA navigation the URL may not have been swapped in yet.
            if (!this.isDirty) {
                this.guardUrl = window.location.href
                this.guardState = JSON.stringify(window.history.state ?? null)
                this.guardIndex = window.navigation?.currentEntry?.index ?? null
            }

            document.body.classList.toggle('fi-ucb-hide-actions', hideFormActions && this.isDirty)
            document.body.classList.toggle('fi-ucb-visible', this.isDirty)
            this.syncLayout()
        },

        // Centre the bar on the main content area (not the window, which is off-centre next to the sidebar).
        syncLayout() {
            const main = document.querySelector('.fi-main')

            if (!this.isDirty || !main || window.innerWidth < 640) {
                document.documentElement.style.removeProperty('--fi-ucb-left')

                return
            }

            const rect = main.getBoundingClientRect()
            document.documentElement.style.setProperty('--fi-ucb-left', `${rect.left + rect.width / 2}px`)
        },

        shake() {
            this.shaking = false
            requestAnimationFrame(() => {
                this.shaking = true
                this.$el.querySelector('.fi-ucb-btn-save')?.focus()
                setTimeout(() => (this.shaking = false), 600)
            })
        },

        // After a back/forward press the address bar shows the destination: step back to this page's own entry.
        restoreHistoryEntry() {
            const here = this.guardIndex
            const there = window.navigation?.currentEntry?.index

            if (typeof here === 'number' && typeof there === 'number' && here !== there) {
                this.restoring = true
                window.history.go(here - there)
                setTimeout(() => (this.restoring = false), 1000)
            } else {
                // No Navigation API: add the page's entry again (history gains one entry, URL stays correct).
                window.history.pushState(JSON.parse(this.guardState), '', this.guardUrl)
            }
        },

        isGuardedLink(anchor) {
            if (anchor.target && anchor.target !== '_self') return false
            if (anchor.hasAttribute('download')) return false

            const url = new URL(anchor.href, window.location.href)

            if (!['http:', 'https:'].includes(url.protocol) || url.origin !== window.location.origin) return false

            // Same-page anchors and javascript-ish links aren't navigation.
            return !(url.pathname === window.location.pathname && url.search === window.location.search && url.hash !== '')
        },

        requestLeave(url, spa) {
            if (navigationGuard === 'modal' && url) {
                this.pendingUrl = url
                this.pendingSpa = spa
                this.toggleModal(true)
            } else {
                this.shake()
            }
        },

        toggleModal(open, id = modalId) {
            window.dispatchEvent(new CustomEvent(open ? 'open-modal' : 'close-modal', { detail: { id } }))
        },

        keepEditing() {
            this.pendingUrl = null
            this.toggleModal(false)
            this.$el.querySelector('.fi-ucb-btn-save')?.focus()
        },

        leave() {
            const url = this.pendingUrl
            const spa = this.pendingSpa
            this.pendingUrl = null
            this.bypassGuard = true
            this.toggleModal(false)

            if (!url) return

            if (spa && window.Livewire?.navigate) window.Livewire.navigate(url)
            else window.location.assign(url)
        },

        async saveAndLeave() {
            const saved = await this.save()

            // A validation error keeps the form dirty: stay, close the modal so the errors are visible.
            if (!saved) {
                this.pendingUrl = null
                this.toggleModal(false)
                this.shake()

                return
            }

            this.leave()
        },

        /** @returns {Promise<boolean>} whether the save went through without validation errors */
        async save() {
            if (this.isSaving) return false

            this.isSaving = true
            let saved = false

            try {
                await this.$wire[saveMethod]()
                await this.$nextTick(() => {})

                saved = this.serverHash() === null
                    ? !document.querySelector('[data-validation-error]')
                    : !this.isDirtyAgainstServer()

                // Without a server-side hash, re-baseline after a save that raised no validation error. Saving can
                // re-render fields (uploads, editors), so take the baseline again once things have settled.
                if (this.serverHash() === null && saved) {
                    this.localBaseline = this.currentHash()
                    this.userInteracted = false
                    this.debounce('rebase', () => {
                        if (this.userInteracted) return
                        this.localBaseline = this.currentHash()
                        this.check()
                    }, 500)
                }
            } finally {
                this.isSaving = false
                this.check()
            }

            return saved
        },

        isDirtyAgainstServer() {
            this.check()

            return this.isDirty && !this.hasRedirect()
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

            // No server-side reset available: confirm in a Filament modal, then reload.
            this.toggleModal(true, discardModalId)
        },

        confirmDiscardReload() {
            this.bypassGuard = true
            this.toggleModal(false, discardModalId)
            window.location.reload()
        },

        cancelDiscard() {
            this.toggleModal(false, discardModalId)
        },
    }
}
