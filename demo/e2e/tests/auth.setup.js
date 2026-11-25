import { test as setup } from '@playwright/test'
import { login } from './helpers.js'

setup('log in as the demo user', async ({ page }) => {
    await login(page)
    await page.context().storageState({ path: '.auth/user.json' })
})
