import { expect, OWNER, test, type Account } from './fixtures'
import { FakeReverb } from './fake/reverb'
import { FAKE_URL, WEB_URL } from './ports'

// Signing in by SMS code (and what the server says when it refuses), the choice of account when a phone holds several, and
// signing out. The Next server's session handlers run for real (/api/session/*) and call the fake backend.

const SECOND_STORE: Account = { ...OWNER, id: 8, name: 'Olim Egamov', role: 'manager', storeId: 2, storeName: 'Shirin Qandolat', branchId: 3, branchName: 'Yunusobod', permissions: ['orders.view', 'orders.manage'] }

async function requestCode(page: import('@playwright/test').Page, phone = '90 123 45 67') {
  await page.goto('/login')
  await page.getByLabel('Telefon raqami').fill(phone)
  await page.getByRole('button', { name: 'Kod yuborish' }).click()
  await expect(page.getByLabel('SMS kod')).toBeVisible()
}

test('a member of staff signs in with a phone number and an SMS code', async ({ page, context, api }) => {
  await requestCode(page)
  expect(api.smsRequests).toEqual(['+998901234567'])

  await page.getByLabel('SMS kod').fill('123456')
  await page.getByRole('button', { name: 'Kirish' }).click()

  // Signed in: the panel opens, with the person's menu
  await expect(page.getByRole('link', { name: 'Buyurtmalar' }).first()).toBeVisible()
  await expect(page).not.toHaveURL(/\/login/)

  // The sign-in asked the backend for a staff account on a web device, and the page holds no refresh token
  const verify = api.callsTo('POST', '/v1/auth/phone/verify')[0]
  expect(verify.body).toMatchObject({ phone: '+998901234567', code: '123456', account: 'staff', device: { type: 'web' } })
  const refresh = (await context.cookies()).find((cookie) => cookie.name === 'rz_refresh')
  expect(refresh?.httpOnly).toBe(true)
  expect(refresh?.sameSite).toBe('Strict')
  expect(await page.evaluate(() => JSON.stringify({ ...localStorage, ...sessionStorage }))).not.toContain('refresh-')
})

test('the session survives a reload and the refresh token is rotated', async ({ page, context, api }) => {
  await requestCode(page)
  await page.getByLabel('SMS kod').fill('123456')
  await page.getByRole('button', { name: 'Kirish' }).click()
  await expect(page.getByRole('link', { name: 'Buyurtmalar' }).first()).toBeVisible()
  const before = (await context.cookies()).find((cookie) => cookie.name === 'rz_refresh')?.value

  await page.reload()
  await expect(page.getByRole('link', { name: 'Buyurtmalar' }).first()).toBeVisible()

  expect(api.callsTo('POST', '/v1/auth/refresh').length).toBeGreaterThanOrEqual(1)
  const after = (await context.cookies()).find((cookie) => cookie.name === 'rz_refresh')?.value
  expect(after).toBeTruthy()
  expect(after).not.toBe(before)
})

test('a wrong code is refused in the backend\'s own words and can be retyped', async ({ page, api }) => {
  await requestCode(page)
  await page.getByLabel('SMS kod').fill('000000')
  await page.getByRole('button', { name: 'Kirish' }).click()

  await expect(page.getByText("Tasdiqlash kodi noto'g'ri.")).toBeVisible()
  await expect(page).toHaveURL(/\/login/)

  await page.getByLabel('SMS kod').fill('123456')
  await page.getByRole('button', { name: 'Kirish' }).click()
  await expect(page.getByRole('link', { name: 'Buyurtmalar' }).first()).toBeVisible()
  expect(api.callsTo('POST', '/v1/auth/phone/verify')).toHaveLength(2)
})

test('a phone that belongs to no member of staff is told so', async ({ page }) => {
  await requestCode(page, '93 555 00 99')
  await page.getByLabel('SMS kod').fill('123456')
  await page.getByRole('button', { name: 'Kirish' }).click()

  await expect(page.getByText("Bu raqam hech qaysi do'kon xodimiga biriktirilmagan", { exact: false })).toBeVisible()
  await expect(page).toHaveURL(/\/login/)
})

test('a phone held by two stores asks which account to enter', async ({ page, api }) => {
  api.accounts.set(OWNER.phone, [OWNER, SECOND_STORE])
  await requestCode(page)
  await page.getByLabel('SMS kod').fill('123456')
  await page.getByRole('button', { name: 'Kirish' }).click()

  const list = page.getByRole('list', { name: 'Qaysi hisobga kirasiz?' })
  await expect(list.getByRole('button')).toHaveCount(2)
  await expect(list.getByRole('button', { name: /Lazzat Oshxona/ })).toContainText('Ega')
  await expect(list.getByRole('button', { name: /Shirin Qandolat/ })).toContainText('Menejer · Yunusobod')

  await list.getByRole('button', { name: /Shirin Qandolat/ }).click()

  // The chosen account signs in: a branch manager sees the orders of the panel, not the owner's whole menu
  await expect(page.getByRole('link', { name: 'Buyurtmalar' }).first()).toBeVisible()
  await expect(page.getByRole('link', { name: 'Menyu' })).toHaveCount(0)
  const select = api.callsTo('POST', '/v1/auth/phone/select-account')[0]
  expect(select.body).toMatchObject({ user_id: SECOND_STORE.id, device: { type: 'web' } })
  expect(String(select.body?.selection_token)).toMatch(/^sel_/)
})

test('signing out ends the session', async ({ page, api, signIn }) => {
  await signIn()
  await page.goto('/orders')
  await expect(page.getByRole('link', { name: 'Buyurtmalar' }).first()).toBeVisible()

  await page.getByRole('button', { name: 'Chiqish' }).first().click()
  await expect(page).toHaveURL(/\/login/)
  expect(api.callsTo('POST', '/v1/auth/logout')).toHaveLength(1)

  // The refresh cookie is gone: opening the panel again asks to sign in
  await page.goto('/orders')
  await expect(page).toHaveURL(/\/login/)
})

test('a refused session (an expired refresh token) sends the browser to the sign-in page', async ({ page, api, context }) => {
  await context.addCookies([
    { name: 'rz_refresh', value: 'refresh-unknown', domain: '127.0.0.1', path: '/api/session', httpOnly: true, sameSite: 'Strict', secure: false },
    { name: 'rz_device', value: '00000000-0000-4000-8000-000000000999', domain: '127.0.0.1', path: '/api/session', httpOnly: true, sameSite: 'Strict', secure: false },
  ])
  await page.goto('/orders')
  await expect(page).toHaveURL(/\/login/)
  expect(api.callsTo('POST', '/v1/auth/refresh')[0].status).toBe(401)
})

test('two tabs share one session: a refresh in one does not sign the other out or start a refresh ping-pong', async ({ page, context, api, signIn, reverb }) => {
  void reverb
  await signIn()
  await page.goto('/menu')
  await expect(page.getByRole('heading', { name: 'Menyu', level: 1 })).toBeVisible()

  // The second tab of the same browser (the same device): it takes the first tab's session instead of rotating it
  const second = await context.newPage()
  await second.route((url) => url.origin !== new URL(WEB_URL).origin && url.origin !== FAKE_URL, (route) => route.fulfill({ status: 204 }))
  await new FakeReverb().install(second)
  await second.goto('/reports')
  await expect(second.getByRole('heading', { name: 'Hisobotlar', level: 1 })).toBeVisible()
  expect(api.callsTo('POST', '/v1/auth/refresh')).toHaveLength(1)

  // The access token runs out; the second tab renews the session and the first adopts it (the backend keeps one live
  // access token per device: had the first tab kept its own, every request of it would now be a 401 and a new refresh)
  api.expireAccessTokens()
  await second.getByRole('link', { name: 'Stop-list' }).first().click()
  await expect(second.getByRole('heading', { name: 'Stop-list', level: 1 })).toBeVisible()
  await expect.poll(() => api.callsTo('POST', '/v1/auth/refresh').length).toBe(2)

  for (const [name, heading] of [['Xodimlar', 'Xodimlar'], ['Hisobotlar', 'Hisobotlar'], ['Menyu', 'Menyu']]) {
    await page.getByRole('link', { name }).first().click()
    await expect(page.getByRole('heading', { name: heading, level: 1 })).toBeVisible()
  }
  await second.getByRole('link', { name: 'Menyu' }).first().click()
  await expect(second.getByRole('heading', { name: 'Menyu', level: 1 })).toBeVisible()

  expect(api.callsTo('POST', '/v1/auth/refresh')).toHaveLength(2)
  expect(api.callsTo('POST', '/v1/auth/refresh').every((call) => call.status === 200)).toBe(true)
  await expect(page).not.toHaveURL(/\/login/)
  await expect(second).not.toHaveURL(/\/login/)
  await second.close()
})

test('signing out of a tab left idle past its access token still ends the backend session', async ({ page, api, signIn }) => {
  await signIn()
  await page.goto('/menu')
  await expect(page.getByRole('heading', { name: 'Menyu', level: 1 })).toBeVisible()
  expect(api.liveRefreshTokens()).toBe(1)

  // The tab's access token is no longer accepted (it sat idle; another tab renewed the session)
  api.expireAccessTokens()
  await page.getByRole('button', { name: 'Chiqish' }).first().click()
  await expect(page).toHaveURL(/\/login/)

  // The server traded the refresh cookie for a fresh token and signed out with it: nothing of the session is left
  expect(api.callsTo('POST', '/v1/auth/logout').map((call) => call.status).at(-1)).toBe(200)
  expect(api.liveRefreshTokens()).toBe(0)
})
