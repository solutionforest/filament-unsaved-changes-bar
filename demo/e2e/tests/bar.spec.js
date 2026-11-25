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

test('SPA navigation while dirty is blocked and the bar shakes', async ({ page }) => {
    await openFirstPost(page)
    const url = page.url()
    let dialogs = 0
    page.on('dialog', (dialog) => {
        dialogs++
        dialog.dismiss()
    })

    await page.getByLabel('Title').fill('Dirty')
    await expectVisible(page)

    await page.locator('.fi-sidebar').getByRole('link', { name: 'Store settings' }).click()
    await expect(bar(page)).toHaveClass(/fi-ucb-shake/)
    await page.waitForTimeout(500)
    expect(page.url()).toBe(url)
    expect(dialogs).toBe(0)
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
