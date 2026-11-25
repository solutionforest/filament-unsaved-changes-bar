import { test, expect } from '@playwright/test'
import { bar, expectHidden, expectVisible, openFirstPost } from './helpers.js'

test('untouched edit form (with RichEditor + FileUpload + Repeater) shows no bar', async ({ page }) => {
    await openFirstPost(page)
    await page.waitForTimeout(1000)
    await expectHidden(page)
})

test('editing shows the bar and reverting by hand hides it', async ({ page }) => {
    await openFirstPost(page)
    const title = page.getByLabel('Title')
    const original = await title.inputValue()

    await title.fill(original + ' (edited)')
    await expectVisible(page)
    await expect(bar(page)).toContainText('Unsaved changes')

    await title.fill(original)
    await expectHidden(page)
})

test('save persists and hides the bar', async ({ page }) => {
    await openFirstPost(page)
    await page.getByLabel('Slug').fill('autumn-roast-' + Date.now())
    await expectVisible(page)

    await bar(page).getByRole('button', { name: 'Save' }).click()
    await expectHidden(page)

    await page.reload()
    await expect(page.getByLabel('Slug')).toHaveValue(/autumn-roast-\d+/)
})

test('a validation error keeps the bar visible', async ({ page }) => {
    await openFirstPost(page)
    await page.getByLabel('Title').fill('')
    await expectVisible(page)

    await bar(page).getByRole('button', { name: 'Save' }).click()
    await expect(page.getByText('The title field is required.')).toBeVisible()
    await expectVisible(page)
})

test('discard restores the saved values and hides the bar', async ({ page }) => {
    await openFirstPost(page)
    const title = page.getByLabel('Title')
    const original = await title.inputValue()

    await title.fill('Something else')
    await page.getByRole('button', { name: 'Add highlight' }).click()
    await expectVisible(page)

    await bar(page).getByRole('button', { name: 'Discard' }).click()
    await expectHidden(page)
    await expect(title).toHaveValue(original)
})

const leaveModal = (page) => page.locator('.fi-modal-window').filter({ hasText: 'Leave without saving?' })
const sidebarLink = (page, name) => page.locator('.fi-sidebar').getByRole('link', { name })

test('leave modal: SPA navigation while dirty opens the modal, Keep editing stays', async ({ page }) => {
    await openFirstPost(page)
    const url = page.url()
    let dialogs = 0
    page.on('dialog', (dialog) => {
        dialogs++
        dialog.dismiss()
    })

    await page.getByLabel('Title').fill('Dirty')
    await expectVisible(page)

    await sidebarLink(page, 'Store settings').click()
    await expect(leaveModal(page)).toBeVisible()
    await expect(leaveModal(page)).toContainText('You have unsaved changes on this page.')

    await leaveModal(page).getByRole('button', { name: 'Keep editing' }).click()
    await expect(leaveModal(page)).toBeHidden()
    expect(page.url()).toBe(url)
    await expect(page.getByLabel('Title')).toHaveValue('Dirty')
    await expectVisible(page)
    expect(dialogs).toBe(0) // no native confirm() on top of the modal
})

test('leave modal: Discard & leave navigates without saving', async ({ page }) => {
    await openFirstPost(page)
    const title = page.getByLabel('Title')
    const original = await title.inputValue()
    const editUrl = page.url()

    await title.fill('Thrown away')
    await sidebarLink(page, 'Store settings').click()
    await leaveModal(page).getByRole('button', { name: 'Discard & leave' }).click()
    await page.waitForURL('**/store-settings')

    await page.goto(editUrl)
    await expect(page.getByLabel('Title')).toHaveValue(original)
})

test('leave modal: Save & leave saves, then navigates', async ({ page }) => {
    await openFirstPost(page)
    const editUrl = page.url()
    const slug = 'saved-and-left-' + Date.now()

    await page.getByLabel('Slug').fill(slug)
    await sidebarLink(page, 'Store settings').click()
    await leaveModal(page).getByRole('button', { name: 'Save & leave' }).click()
    await page.waitForURL('**/store-settings')

    await page.goto(editUrl)
    await expect(page.getByLabel('Slug')).toHaveValue(slug)
})

test('leave modal: Save & leave with a validation error stays on the page', async ({ page }) => {
    await openFirstPost(page)
    const url = page.url()

    await page.getByLabel('Title').fill('')
    await sidebarLink(page, 'Store settings').click()
    await leaveModal(page).getByRole('button', { name: 'Save & leave' }).click()

    await expect(page.getByText('The title field is required.')).toBeVisible()
    await expect(leaveModal(page)).toBeHidden()
    expect(page.url()).toBe(url)
    await expectVisible(page)
})

test('shake mode (2nd panel): SPA navigation while dirty is blocked and the bar shakes', async ({ page }) => {
    await page.goto('/manager/posts')
    await page.getByRole('link', { name: 'Introducing our autumn roast' }).first().click()
    await page.waitForURL('**/manager/posts/*/edit')
    await page.waitForTimeout(800)
    const url = page.url()

    await page.getByLabel('Title').fill('Dirty')
    await expectVisible(page)
    await sidebarLink(page, 'Dashboard').click()
    await expect(bar(page)).toHaveClass(/fi-ucb-shake/)
    await page.waitForTimeout(500)
    expect(page.url()).toBe(url)
    await expect(page.locator('.fi-modal-window')).toHaveCount(0)
})

test('a full reload while dirty triggers the native beforeunload dialog', async ({ page }) => {
    await openFirstPost(page)
    await page.getByLabel('Title').fill('Dirty')
    await expectVisible(page)

    const dialog = new Promise((resolve) => page.once('dialog', (d) => { resolve(d.type()); d.dismiss() }))
    await page.evaluate(() => location.reload()).catch(() => {})
    expect(await dialog).toBe('beforeunload')
})

test('create page: typing shows the bar, discard resets it', async ({ page }) => {
    await page.goto('/admin/posts/create')
    await page.waitForTimeout(800)
    await expectHidden(page)

    await page.getByLabel('Title').fill('Draft post')
    await expectVisible(page)

    await bar(page).getByRole('button', { name: 'Discard' }).click()
    await expectHidden(page)
    await expect(page.getByLabel('Title')).toHaveValue('')
})

test('custom settings page: save and discard work', async ({ page }) => {
    await page.goto('/admin/store-settings')
    await page.waitForTimeout(800)
    await expectHidden(page)

    const name = page.getByLabel('Store name')
    const original = await name.inputValue()
    await name.fill('Renamed store')
    await expectVisible(page)
    await bar(page).getByRole('button', { name: 'Discard' }).click()
    await expectHidden(page)
    await expect(name).toHaveValue(original)

    await name.fill(original + '!')
    await bar(page).getByRole('button', { name: 'Save' }).click()
    await expectHidden(page)
    await page.reload()
    await expect(name).toHaveValue(original + '!')
    await name.fill(original)
    await bar(page).getByRole('button', { name: 'Save' }).click()
    await expectHidden(page)
})

test('multi-panel: the /manager panel uses its own configuration', async ({ page }) => {
    // Same Post resource, second panel: bar at the top, success/danger buttons, only on Edit (includePages).
    await page.goto('/manager/posts')
    await page.getByRole('link', { name: 'Introducing our autumn roast' }).first().click()
    await page.waitForURL('**/manager/posts/*/edit')
    await page.waitForTimeout(800)
    await expectHidden(page)

    await page.getByLabel('Title').fill('Edited in the manager panel')
    await expectVisible(page)
    await expect(bar(page)).toHaveClass(/fi-ucb-top/)
    await expect(bar(page).getByRole('button', { name: 'Save' })).toHaveClass(/fi-color-success/)
    await bar(page).getByRole('button', { name: 'Discard' }).click()
    await expectHidden(page)

    await page.goto('/manager/posts/create')
    await expect(page.locator('[data-unsaved-changes-bar]')).toHaveCount(0)
})

// ---- Non-SPA panel, no-trait discard modal, layout and mobile ----

async function openClassicPlainPost(page) {
    await page.goto('/classic/plain-posts')
    await page.getByRole('link', { name: 'Introducing our autumn roast' }).first().click()
    await page.waitForURL('**/classic/plain-posts/*/edit')
    await page.waitForTimeout(800)
}

test('non-SPA panel: clicking a sidebar link while dirty opens the leave modal', async ({ page }) => {
    await openClassicPlainPost(page)
    const url = page.url()
    let dialogs = 0
    page.on('dialog', (d) => { dialogs++; d.dismiss() })

    await page.getByLabel('Title').fill('Dirty')
    await expectVisible(page)
    await sidebarLink(page, 'Dashboard').click()

    await expect(leaveModal(page)).toBeVisible()
    expect(page.url()).toBe(url)
    expect(dialogs).toBe(0) // no native beforeunload dialog: we intercepted the click

    await leaveModal(page).getByRole('button', { name: 'Keep editing' }).click()
    await expect(leaveModal(page)).toBeHidden()
    await expect(page.getByLabel('Title')).toHaveValue('Dirty')
})

test('non-SPA panel: Discard & leave performs a normal page load', async ({ page }) => {
    await openClassicPlainPost(page)
    await page.getByLabel('Title').fill('Thrown away')
    await sidebarLink(page, 'Dashboard').click()
    await leaveModal(page).getByRole('button', { name: 'Discard & leave' }).click()
    await page.waitForURL('**/classic')
})

test('non-SPA panel: Save & leave saves, then loads the link', async ({ page }) => {
    await openClassicPlainPost(page)
    const editUrl = page.url()
    const slug = 'classic-saved-' + Date.now()
    await page.getByLabel('Slug').fill(slug)
    await sidebarLink(page, 'Dashboard').click()
    await leaveModal(page).getByRole('button', { name: 'Save & leave' }).click()
    await page.waitForURL('**/classic')

    await page.goto(editUrl)
    await expect(page.getByLabel('Slug')).toHaveValue(slug)
})

test('page without the trait: Discard asks in a Filament modal (no native confirm), then reloads', async ({ page }) => {
    await openClassicPlainPost(page)
    const title = page.getByLabel('Title')
    const original = await title.inputValue()
    let dialogs = 0
    page.on('dialog', (d) => { dialogs++; d.accept() })

    await title.fill('Something else')
    await bar(page).getByRole('button', { name: 'Discard' }).click()

    const modal = page.locator('.fi-modal-window').filter({ hasText: 'Discard all changes?' })
    await expect(modal).toBeVisible()

    await modal.getByRole('button', { name: 'Cancel' }).click()
    await expect(modal).toBeHidden()
    await expect(title).toHaveValue('Something else')

    await bar(page).getByRole('button', { name: 'Discard' }).click()
    await modal.getByRole('button', { name: 'Discard' }).click()
    await expect(page.getByLabel('Title')).toHaveValue(original)
    await expectHidden(page)
    expect(dialogs).toBe(0)
})

test('layout: the bar is centred on the content area and leaves room below the form', async ({ page }) => {
    await openFirstPost(page)
    await page.getByLabel('Title').fill('Dirty')
    await expectVisible(page)
    await page.waitForTimeout(400)

    const barBox = await bar(page).boundingBox()
    const mainBox = await page.locator('.fi-main').boundingBox()
    const barCentre = barBox.x + barBox.width / 2
    const mainCentre = mainBox.x + mainBox.width / 2
    expect(Math.abs(barCentre - mainCentre)).toBeLessThan(4)

    // The last element of the page can be scrolled clear of the bar.
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
    const lastButton = page.locator('.fi-page form').getByRole('button', { name: /^Save changes$/ })
    await lastButton.scrollIntoViewIfNeeded()
    const lastBox = await lastButton.boundingBox()
    const barNow = await bar(page).boundingBox()
    expect(lastBox.y + lastBox.height).toBeLessThanOrEqual(barNow.y + 1)
})

test.describe('mobile', () => {
    test.use({ viewport: { width: 390, height: 800 } })

    test('the bar spans the width and the leave modal buttons stack', async ({ page }) => {
        await openFirstPost(page)
        await page.getByLabel('Title').fill('Dirty')
        await expectVisible(page)
        await page.waitForTimeout(400)

        const box = await bar(page).boundingBox()
        expect(box.x).toBeLessThan(16)
        expect(box.x + box.width).toBeGreaterThan(390 - 16)
        await expect(bar(page).getByRole('button', { name: 'Save' })).toBeInViewport()
        await expect(bar(page).getByRole('button', { name: 'Discard' })).toBeInViewport()
        await page.screenshot({ path: '../../docs/images/mobile-bar.png' })

        await page.locator('.fi-topbar-open-sidebar-btn').click()
        await sidebarLink(page, 'Store settings').click()
        await expect(leaveModal(page)).toBeVisible()
        const keep = await leaveModal(page).getByRole('button', { name: 'Keep editing' }).boundingBox()
        const save = await leaveModal(page).getByRole('button', { name: 'Save & leave' }).boundingBox()
        expect(Math.abs(keep.x - save.x)).toBeLessThan(4)
        expect(save.y).toBeGreaterThan(keep.y + keep.height - 1)
        await page.screenshot({ path: '../../docs/images/mobile-leave-modal.png' })
        await leaveModal(page).getByRole('button', { name: 'Keep editing' }).click()
    })
})
