// Renders cover.html to docs/images/cover.png (1280×640 @2x) and docs/images/cover-filament.png (2560×1440).
// Run from the package root after `cd demo/e2e && npm ci`: node docs/cover/render.mjs
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const here = path.dirname(fileURLToPath(import.meta.url))
const require = createRequire(path.join(here, '../../demo/e2e/package.json'))
const { chromium } = require('@playwright/test')

const browser = await chromium.launch()
const url = 'file://' + path.join(here, 'cover.html')

const wide = await browser.newPage({ viewport: { width: 1280, height: 640 }, deviceScaleFactor: 2 })
await wide.goto(url)
await wide.evaluate(() => document.fonts.ready)
await wide.screenshot({ path: path.join(here, '../images/cover.png') })

// Filament plugin directory: 16:9. Same art, centred vertically on the dark background.
const dir = await browser.newPage({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 2 })
await dir.goto(url)
await dir.addStyleTag({ content: 'body{height:720px} .left{top:114px} .chips{top:492px} .win{top:124px} .modal{top:358px} .foot{bottom:52px}' })
await dir.evaluate(() => document.fonts.ready)
await dir.screenshot({ path: path.join(here, '../images/cover-filament.png') })

await browser.close()
