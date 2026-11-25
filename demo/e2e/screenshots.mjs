// Regenerates the README screenshots and GIF from the running demo app.
//   DEMO_URL=http://127.0.0.1:8000 node screenshots.mjs
import { chromium } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { mkdirSync, readdirSync, rmSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const base = process.env.DEMO_URL ?? 'http://127.0.0.1:8000'
const out = join(dirname(fileURLToPath(import.meta.url)), '../../docs/images')
const videoDir = join(out, '.video')
mkdirSync(out, { recursive: true })

const browser = await chromium.launch()
const viewport = { width: 1280, height: 760 }

async function newPage({ dark = false, video = false } = {}) {
    const context = await browser.newContext({
        viewport,
        deviceScaleFactor: video ? 1 : 2,
        colorScheme: dark ? 'dark' : 'light',
        ...(video ? { recordVideo: { dir: videoDir, size: viewport } } : {}),
    })
    const page = await context.newPage()
    await page.goto(`${base}/admin/login`)
    await page.evaluate((theme) => localStorage.setItem('theme', theme), dark ? 'dark' : 'light')
    await page.getByRole('textbox', { name: 'Email address' }).fill('demo@example.com')
    await page.getByRole('textbox', { name: 'Password' }).fill('password')
    await page.getByRole('button', { name: 'Sign in' }).click()
    await page.waitForURL('**/admin')
    return page
}

const bar = (page) => page.locator('[data-unsaved-changes-bar] [role="status"]')

async function openPost(page) {
    await page.goto(`${base}/admin/posts`)
    await page.getByRole('link', { name: 'Introducing our autumn roast' }).first().click()
    await page.waitForURL('**/edit')
    await page.waitForTimeout(1000)
}

// 1. Light mode, bar visible.
{
    const page = await newPage()
    await openPost(page)
    await page.getByLabel('Title').fill('Introducing our autumn roast — now in stores')
    await bar(page).waitFor()
    await page.waitForTimeout(400)
    await page.screenshot({ path: join(out, 'bar-light.png') })
    await bar(page).screenshot({ path: join(out, 'bar-closeup.png') })

    // 2. Blocked SPA navigation: the bar shakes and is highlighted.
    await page.locator('.fi-sidebar').getByRole('link', { name: 'Store settings' }).click()
    await page.waitForTimeout(250)
    await page.screenshot({ path: join(out, 'bar-navigation-blocked.png') })
    await page.context().close()
}

// 3. Dark mode.
{
    const page = await newPage({ dark: true })
    await openPost(page)
    await page.getByLabel('Slug').fill('autumn-roast-2026')
    await bar(page).waitFor()
    await page.waitForTimeout(400)
    await page.screenshot({ path: join(out, 'bar-dark.png') })
    await page.context().close()
}

// 4. Custom settings page.
{
    const page = await newPage()
    await page.goto(`${base}/admin/store-settings`)
    await page.waitForTimeout(1000)
    await page.getByLabel('Store name').fill('Forest Coffee Co. (HK)')
    await bar(page).waitFor()
    await page.waitForTimeout(400)
    await page.screenshot({ path: join(out, 'custom-page.png') })
    await bar(page).getByRole('button', { name: 'Discard' }).click()
    await page.context().close()
}

// 5. Animated walkthrough: edit → bar → discard → edit → save.
{
    rmSync(videoDir, { recursive: true, force: true })
    const page = await newPage({ video: true })
    await openPost(page)
    const title = page.getByLabel('Title')
    const original = await title.inputValue()
    await title.click()
    await page.keyboard.press('End')
    await page.keyboard.type(' — limited batch', { delay: 60 })
    await page.waitForTimeout(1200)
    await bar(page).getByRole('button', { name: 'Discard' }).hover()
    await page.waitForTimeout(400)
    await bar(page).getByRole('button', { name: 'Discard' }).click()
    await page.waitForTimeout(1200)
    await page.getByLabel('Slug').click()
    await page.keyboard.press('End')
    await page.keyboard.type('-2026', { delay: 60 })
    await page.waitForTimeout(800)
    await page.locator('.fi-sidebar').getByRole('link', { name: 'Store settings' }).click()
    await page.waitForTimeout(1200)
    await bar(page).getByRole('button', { name: 'Save' }).click()
    await page.waitForTimeout(1800)
    // Restore the seed value so reruns are stable.
    await page.getByLabel('Slug').fill(original ? 'introducing-our-autumn-roast' : '')
    await bar(page).getByRole('button', { name: 'Save' }).click()
    await page.waitForTimeout(500)
    await page.context().close()

    const [video] = readdirSync(videoDir).filter((f) => f.endsWith('.webm'))
    // Trim the login redirect off the start, then build a palette-optimised GIF.
    execFileSync('ffmpeg', [
        '-y', '-loglevel', 'error', '-ss', '2.5', '-i', join(videoDir, video),
        '-vf', 'fps=12,scale=960:-1:flags=lanczos,split[a][b];[a]palettegen=max_colors=128[p];[b][p]paletteuse=dither=bayer',
        join(out, 'demo.gif'),
    ])
    rmSync(videoDir, { recursive: true, force: true })
}

await browser.close()
console.log(`Screenshots written to ${out}`)
