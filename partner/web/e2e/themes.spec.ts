import type { Page } from '@playwright/test'
import { expect, test } from './fixtures'

// Light and dark smoke: every main page opens in both colour schemes (the panel follows the system setting), shows its
// heading, throws nothing, does not scroll sideways, and its heading is readable against the page background.

const PAGES: Array<{ path: string; heading: string }> = [
  { path: '/dashboard', heading: 'Boshqaruv paneli' },
  { path: '/orders', heading: 'Buyurtmalar' },
  { path: '/menu', heading: 'Menyu' },
  { path: '/stoplist', heading: 'Stop-list' },
  { path: '/staff', heading: 'Xodimlar' },
  { path: '/zones', heading: 'Yetkazish zonalari' },
  { path: '/reports', heading: 'Hisobotlar' },
  { path: '/billing', heading: "Tarif va to'lov" },
]

// WCAG relative luminance of a computed "rgb(a)" / "color(srgb ...)" colour
async function contrastOfHeading(page: Page, heading: string): Promise<{ ratio: number; background: number }> {
  return page.evaluate((name) => {
    const parse = (value: string): [number, number, number] => {
      const canvas = document.createElement('canvas')
      canvas.width = canvas.height = 1
      const context = canvas.getContext('2d')!
      context.fillStyle = '#000'
      context.fillStyle = value
      context.fillRect(0, 0, 1, 1)
      const [r, g, b] = context.getImageData(0, 0, 1, 1).data
      return [r, g, b]
    }
    const luminance = ([r, g, b]: [number, number, number]) => {
      const channel = (v: number) => {
        const s = v / 255
        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
      }
      return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)
    }
    const h1 = [...document.querySelectorAll('h1')].find((element) => element.textContent?.trim() === name)
    if (!h1) return { ratio: 0, background: -1 }
    // The first opaque background up the tree
    let node: Element | null = h1
    let background = 'rgb(255, 255, 255)'
    while (node) {
      const value = getComputedStyle(node).backgroundColor
      if (value && value !== 'rgba(0, 0, 0, 0)' && value !== 'transparent') {
        background = value
        break
      }
      node = node.parentElement
    }
    const back = luminance(parse(background))
    const front = luminance(parse(getComputedStyle(h1).color))
    const [light, dark] = back > front ? [back, front] : [front, back]
    return { ratio: (light + 0.05) / (dark + 0.05), background: back }
  }, heading)
}

for (const scheme of ['light', 'dark'] as const) {
  test(`every main page opens in the ${scheme} scheme`, async ({ page, signIn, reverb }) => {
    void reverb
    await page.emulateMedia({ colorScheme: scheme })
    await signIn()

    let backdrop = -1
    for (const { path, heading } of PAGES) {
      await page.goto(path)
      await expect(page.getByRole('heading', { name: heading, level: 1 })).toBeVisible()
      // Nothing scrolls sideways (the board's own columns scroll inside their box)
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), `${path} scrolls sideways`).toBe(true)

      const { ratio, background } = await contrastOfHeading(page, heading)
      expect(ratio, `${path}: the heading is hard to read in ${scheme}`).toBeGreaterThanOrEqual(4.5)
      backdrop = background
    }
    // The scheme really changed the page: a dark page is dark, a light one light
    if (scheme === 'dark') expect(backdrop).toBeLessThan(0.2)
    else expect(backdrop).toBeGreaterThan(0.6)
  })
}

test('the sign-in page follows the scheme too', async ({ page }) => {
  for (const scheme of ['light', 'dark'] as const) {
    await page.emulateMedia({ colorScheme: scheme })
    await page.goto('/login')
    await expect(page.getByRole('button', { name: 'Kod yuborish' })).toBeVisible()
    const luminance = await page.evaluate(() => {
      const [r, g, b] = getComputedStyle(document.body).backgroundColor.match(/\d+/g)!.map(Number)
      return (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255
    })
    if (scheme === 'dark') expect(luminance).toBeLessThan(0.3)
    else expect(luminance).toBeGreaterThan(0.6)
  }
})
