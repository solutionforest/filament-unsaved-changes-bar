import { expect } from '@playwright/test'

export async function login(page) {
    await page.goto('/admin/login')
    await page.getByRole('textbox', { name: 'Email address' }).fill('demo@example.com')
    await page.getByRole('textbox', { name: 'Password' }).fill('password')
    await page.getByRole('button', { name: 'Sign in' }).click()
    await page.waitForURL('**/admin')
}

export function bar(page) {
    return page.locator('[data-unsaved-changes-bar] [role="status"]')
}

export async function openFirstPost(page) {
    await page.goto('/admin/posts')
    await page.getByRole('link', { name: 'Introducing our autumn roast' }).first().click()
    await page.waitForURL('**/posts/*/edit')
    await page.waitForTimeout(800) // let the 500 ms re-baseline settle
}

export async function expectHidden(page) {
    await expect(bar(page)).toBeHidden()
}

export async function expectVisible(page) {
    await expect(bar(page)).toBeVisible()
}
