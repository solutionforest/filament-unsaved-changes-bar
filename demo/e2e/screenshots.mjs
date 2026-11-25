// Regenerates the README screenshots, GIF and MP4 from the running demo app (needs ffmpeg).
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
const leaveModal = (page) => page.locator('.fi-modal-window').filter({ hasText: 'Leave without saving?' })
const sidebarLink = (page, name) => page.locator('.fi-sidebar').getByRole('link', { name })

async function openPost(page, panel = 'admin') {
    await page.goto(`${base}/${panel}/posts`)
    await page.getByRole('link', { name: 'Introducing our autumn roast' }).first().click()
    await page.waitForURL('**/edit')
    await page.waitForTimeout(1000)
}

// 1. Light mode: the bar, a close-up, and the leave modal.
{
    const page = await newPage()
    await openPost(page)
    await page.getByLabel('Title').fill('Introducing our autumn roast — now in stores')
    await bar(page).waitFor()
    await page.waitForTimeout(400)
    await page.screenshot({ path: join(out, 'bar-light.png') })
    await bar(page).screenshot({ path: join(out, 'bar-closeup.png') })

    await sidebarLink(page, 'Store settings').click()
    await leaveModal(page).waitFor()
    await page.waitForTimeout(400)
    await page.screenshot({ path: join(out, 'leave-modal.png') })
    await leaveModal(page).getByRole('button', { name: 'Keep editing' }).click()
    await page.context().close()
}

// 2. Dark mode.
{
    const page = await newPage({ dark: true })
    await openPost(page)
    await page.getByLabel('Slug').fill('autumn-roast-2026')
    await bar(page).waitFor()
    await page.waitForTimeout(400)
    await page.screenshot({ path: join(out, 'bar-dark.png') })
    await page.context().close()
}

// 3. Custom settings page.
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

// 4. Second panel: its own configuration (top, success/danger buttons, shake guard, indigo theme).
{
    const page = await newPage()
    await openPost(page, 'manager')
    await page.getByLabel('Title').fill('Edited in the manager panel')
    await bar(page).waitFor()
    await page.waitForTimeout(400)
    await page.screenshot({ path: join(out, 'multi-panel.png') })
    await sidebarLink(page, 'Dashboard').click()
    await page.waitForTimeout(250)
    await page.screenshot({ path: join(out, 'bar-navigation-shake.png') })
    await bar(page).getByRole('button', { name: 'Discard' }).click()
    await page.context().close()
}

// 5. Walkthrough video: edit → discard → edit → click away → modal → Save & leave.
{
    rmSync(videoDir, { recursive: true, force: true })
    const page = await newPage({ video: true })
    await openPost(page)
    const title = page.getByLabel('Title')
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
    await page.waitForTimeout(1000)
    await sidebarLink(page, 'Store settings').hover()
    await page.waitForTimeout(300)
    await sidebarLink(page, 'Store settings').click()
    await leaveModal(page).waitFor()
    await page.waitForTimeout(1500)
    await leaveModal(page).getByRole('button', { name: 'Save & leave' }).hover()
    await page.waitForTimeout(400)
    await leaveModal(page).getByRole('button', { name: 'Save & leave' }).click()
    await page.waitForURL('**/store-settings')
    await page.waitForTimeout(1800)
    // Restore the seed value so reruns are stable.
    await openPost(page)
    await page.getByLabel('Slug').fill('introducing-our-autumn-roast')
    await bar(page).getByRole('button', { name: 'Save' }).click()
    await page.waitForTimeout(500)
    const video = page.video()
    await page.context().close()
    const source = await video.path()

    // Trim the login redirect at the start and the restore step at the end.
    const trim = ['-ss', '2.5', '-t', '15.5']
    execFileSync('ffmpeg', ['-y', '-loglevel', 'error', ...trim, '-i', source,
        '-vf', 'fps=12,scale=960:-1:flags=lanczos,split[a][b];[a]palettegen=max_colors=128[p];[b][p]paletteuse=dither=bayer',
        join(out, 'demo.gif')])
    execFileSync('ffmpeg', ['-y', '-loglevel', 'error', ...trim, '-i', source,
        '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-crf', '23', '-preset', 'slow', '-movflags', '+faststart', '-an',
        join(out, 'demo.mp4')])
    rmSync(videoDir, { recursive: true, force: true })
}

await browser.close()
console.log(`Screenshots and videos written to ${out}`)
