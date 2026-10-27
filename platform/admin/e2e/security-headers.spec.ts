import type { Page } from '@playwright/test'
import { expect, openSignedIn, test, VALID_CODE } from './fixtures'
import { PASSWORD } from './fake/backend'

// The security headers of next.config.ts on the production build (review hardening): the Content-Security-Policy is
// there, and nothing the app does breaks it (Next's inline bootstrap, the inline QR SVG of the 2FA setup, the pages).
// A violation shows up as a `securitypolicyviolation` event (recorded before any of the page's own code runs) and on the
// console, on every page the test opens.

async function watchViolations(page: Page): Promise<() => string[]> {
  const seen: string[] = []
  await page.exposeFunction('__reportCspViolation', (violation: string) => seen.push(violation))
  await page.addInitScript(() => {
    document.addEventListener('securitypolicyviolation', (event) => {
      ;(window as unknown as { __reportCspViolation: (violation: string) => void }).__reportCspViolation(`${event.violatedDirective} ${event.blockedURI}`)
    })
  })
  // Chromium also says it on the console ("Refused to ... because it violates the following Content Security Policy")
  page.on('console', (message) => {
    if (/Content Security Policy|Refused to/i.test(message.text())) seen.push(message.text())
  })
  return () => [...seen]
}

test('every page carries the policy and the security headers', async ({ page, api }) => {
  void api
  const response = await page.goto('/login')
  const headers = response?.headers() ?? {}
  const csp = headers['content-security-policy'] ?? ''
  expect(csp).toContain("default-src 'self'")
  expect(csp).toContain("object-src 'none'")
  expect(csp).toContain("frame-ancestors 'none'")
  expect(csp).toContain("base-uri 'self'")
  expect(csp).toContain("form-action 'self'")
  // The production build needs no eval
  expect(csp).not.toContain('unsafe-eval')
  expect(headers['x-frame-options']).toBe('DENY')
  expect(headers['x-content-type-options']).toBe('nosniff')
  expect(headers['cross-origin-opener-policy']).toBe('same-origin')
  expect(headers['permissions-policy']).toContain('camera=()')

  // The proxy's answers carry it too
  const proxied = await page.request.get('/api/backend/v1/admin/me')
  expect(proxied.headers()['content-security-policy']).toBe(csp)
})

test('the first sign-in with the 2FA setup breaks no policy', async ({ page, api }) => {
  const violations = await watchViolations(page)

  // First sign-in: the setup screen draws the server's QR code (inline SVG) and shows the recovery codes
  await page.goto('/login')
  await page.getByLabel('Email').fill('madina@rizqim.uz')
  await page.getByLabel('Parol', { exact: true }).fill(PASSWORD)
  await page.getByRole('button', { name: 'Kirish' }).click()
  await expect(page.getByRole('img', { name: 'Ilovaga ulash uchun QR kod' })).toBeVisible()
  expect(api.callsTo('POST', '/auth/2fa/setup')).toHaveLength(1)
  await page.getByLabel('6 xonali kod').fill(VALID_CODE)
  await page.getByRole('button', { name: 'Yoqish' }).click()
  await expect(page.getByRole('heading', { name: 'Tiklash kodlaringizni saqlang' })).toBeVisible()
  await page.getByRole('checkbox', { name: "Saqlab qo'ydim" }).check()
  await page.getByRole('button', { name: "Boshqaruv markaziga o'tish" }).click()
  await expect(page.getByRole('heading', { name: 'Buyurtmalar', level: 1 })).toBeVisible()

  expect(violations()).toEqual([])
})

test('the signed-in sections break no policy', async ({ page, signIn }) => {
  const violations = await watchViolations(page)
  await openSignedIn(page, signIn, '/today')
  await expect(page.getByRole('heading', { name: 'Bugun', level: 1 })).toBeVisible()
  for (const [path, heading] of [['/orders', 'Buyurtmalar'], ['/monitoring', 'Monitoring'], ['/team', 'Jamoa'], ['/roles', 'Rollar'], ['/audit', 'Harakatlar jurnali'], ['/sessions', 'Sessiyalarim']] as const) {
    await page.goto(path)
    await expect(page.getByRole('heading', { name: heading, level: 1 })).toBeVisible()
  }
  expect(violations()).toEqual([])
})
