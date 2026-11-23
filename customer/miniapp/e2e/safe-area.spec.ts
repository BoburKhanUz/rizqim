import type { Page } from '@playwright/test'
import { expect, test } from './fixtures'

// Wave P: Telegram's full-screen mode (Bot API 8.0). The Mini App is drawn under the phone's status bar and under
// Telegram's own "Close" / "⌄ ⋯" buttons; Telegram reports both as --tg-safe-area-inset-* (the device) and
// --tg-content-safe-area-inset-* (its buttons). The header, the sheets and the bottom dock must keep clear of them.
// The values are the ones of an iPhone in portrait: 47 px status bar + 46 px button row, 34 px home indicator.
//
// MINIAPP_SHOTS_DIR=<dir> also saves a screenshot of each mode and theme there.

const FULLSCREEN = { top: 47, bottom: 34, contentTop: 46 }

// What telegram-web-app.js sets on <html> (the e2e page has no Telegram client, so it is set here)
async function setTelegramInsets(page: Page, insets: typeof FULLSCREEN | null) {
  await page.evaluate((value) => {
    const style = document.documentElement.style
    for (const side of ['top', 'bottom', 'left', 'right']) {
      style.setProperty(`--tg-safe-area-inset-${side}`, '0px')
      style.setProperty(`--tg-content-safe-area-inset-${side}`, '0px')
    }
    if (!value) return
    style.setProperty('--tg-safe-area-inset-top', `${value.top}px`)
    style.setProperty('--tg-safe-area-inset-bottom', `${value.bottom}px`)
    style.setProperty('--tg-content-safe-area-inset-top', `${value.contentTop}px`)
  }, insets)
}

const logo = (page: Page) => page.getByRole('heading', { level: 1, name: 'Rizqim' }).getByRole('img', { name: 'Rizqim' })

async function box(locator: ReturnType<Page['locator']>) {
  const rect = await locator.boundingBox()
  expect(rect).not.toBeNull()
  return rect!
}

// In full-screen the shot also draws a stand-in for what Telegram puts on top (the status bar and its "Yopish" /
// "⌄ ⋯" buttons), so the screenshot shows what the user sees; it is removed again right after.
async function shoot(page: Page, name: string, fullscreen = false) {
  const dir = process.env.MINIAPP_SHOTS_DIR
  if (!dir) return
  // Not halfway through a fade-in or the sheet sliding up
  await page.evaluate(() => Promise.all(document.getAnimations().map((animation) => animation.finished.catch(() => null))))
  if (fullscreen) {
    await page.evaluate(({ top, contentTop }) => {
      const overlay = document.createElement('div')
      overlay.id = 'e2e-telegram-chrome'
      overlay.style.cssText = 'position:fixed;inset:0 0 auto 0;z-index:9999;pointer-events:none;font:600 15px system-ui'
      const pill = 'position:absolute;height:32px;border-radius:16px;background:rgba(60,60,67,.55);color:#fff;display:flex;align-items:center;padding:0 14px'
      const buttonTop = top + (contentTop - 32) / 2
      overlay.innerHTML =
        `<div style="height:${top}px;display:flex;align-items:center;justify-content:space-between;padding:0 28px;color:currentColor">` +
        '<span>9:41</span><span>5G 100%</span></div>' +
        `<div style="${pill};left:16px;top:${buttonTop}px">Yopish</div>` +
        `<div style="${pill};right:16px;top:${buttonTop}px">⌄ ⋯</div>`
      overlay.style.color = getComputedStyle(document.body).color
      document.body.appendChild(overlay)
    }, FULLSCREEN)
  }
  await page.screenshot({ path: `${dir}/${name}.png` })
  if (fullscreen) await page.evaluate(() => document.getElementById('e2e-telegram-chrome')?.remove())
}

for (const scheme of ['light', 'dark'] as const) {
  test(`normal mode (${scheme}): no insets, the header with the logo sits at the top`, async ({ page, api }) => {
    void api
    await page.emulateMedia({ colorScheme: scheme })
    await page.goto('/')
    await setTelegramInsets(page, null)
    await expect(logo(page)).toBeVisible()
    expect((await box(logo(page))).y).toBeLessThan(24)
    expect((await box(page.getByTestId('top-inset-band'))).height).toBe(0)
    await shoot(page, `normal-${scheme}`)
  })

  test(`full-screen (${scheme}): the header, sheet and dock keep clear of the status bar and Telegram's buttons`, async ({ page, api }) => {
    void api
    await page.emulateMedia({ colorScheme: scheme })
    await page.goto('/')
    await setTelegramInsets(page, FULLSCREEN)
    const insetTop = FULLSCREEN.top + FULLSCREEN.contentTop

    // The header starts below the 93 px band, the band covers exactly it
    await expect(logo(page)).toBeVisible()
    expect((await box(logo(page))).y).toBeGreaterThanOrEqual(insetTop)
    const band = await box(page.getByTestId('top-inset-band'))
    expect(band.y).toBe(0)
    expect(band.height).toBe(insetTop)

    // The bottom navigation ends above the home indicator
    const nav = page.getByTestId('bottom-dock').getByRole('navigation')
    const navBox = await box(nav)
    const navPadding = await nav.evaluate((element) => getComputedStyle(element).paddingBottom)
    expect(navPadding).toBe(`${FULLSCREEN.bottom}px`)
    expect(navBox.y + navBox.height).toBeLessThanOrEqual(844)
    await shoot(page, `fullscreen-${scheme}`, true)

    // Scrolled: the content passes under the band, never under the status bar or Telegram's buttons
    await page.mouse.wheel(0, 600)
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(0)
    await shoot(page, `fullscreen-${scheme}-scrolled`, true)

    // A bottom sheet never reaches into the band
    await page.getByRole('article').filter({ has: page.getByRole('heading', { name: 'Burger', exact: true }) }).getByRole('button', { name: "Savatga qo'shish" }).click()
    const sheet = page.getByRole('dialog', { name: 'Burger' })
    await expect(sheet).toBeVisible()
    expect((await box(sheet)).y).toBeGreaterThanOrEqual(insetTop)
    await shoot(page, `fullscreen-${scheme}-sheet`, true)
  })
}
