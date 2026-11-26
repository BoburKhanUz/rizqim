import type { Page } from '@playwright/test'
import { makeUser } from '../src/test/factories'
import { TELEGRAM_ID } from './fakeApi'
import { expect, relaunchAs, test } from './fixtures'

// "Click = consent" (docs/legal/README.md): a customer's first launch shows, once, that using the app accepts the Terms
// of Use and the Privacy Policy. Remembered per Telegram user (consent-storage:<telegram id>), not per device.

const TERMS = 'https://rizqim.uz/shartlar'
const PRIVACY = 'https://rizqim.uz/maxfiylik'
const NOTICE = 'Ilovadan foydalanib, siz Foydalanish shartlari va Maxfiylik siyosatiga rozilik bildirasiz.'

// Telegram opens the documents outside the Mini App (WebApp.openLink): record what it is asked to open
async function recordOpenLink(page: Page): Promise<void> {
  await page.evaluate(() => {
    const opened: string[] = []
    ;(window as unknown as { __opened: string[] }).__opened = opened
    const webApp = (window as unknown as { Telegram: { WebApp: { openLink: (url: string) => void } } }).Telegram.WebApp
    webApp.openLink = (url: string) => opened.push(url)
  })
}
const openedLinks = (page: Page) => page.evaluate(() => (window as unknown as { __opened: string[] }).__opened)

test('the first launch shows the consent line once; another Telegram account on the device sees it on its own first launch', async ({ page, api }) => {
  await page.goto('/')

  const notice = page.getByTestId('consent-notice')
  await expect(notice).toBeVisible()
  await expect(notice).toHaveText(NOTICE)
  // Unobtrusive: a line in the bottom dock, above the tabs
  await expect(page.getByTestId('bottom-dock').getByTestId('consent-notice')).toBeVisible()

  await recordOpenLink(page)
  await notice.getByRole('link', { name: 'Foydalanish shartlari' }).click()
  await notice.getByRole('link', { name: 'Maxfiylik siyosati' }).click()
  expect(await openedLinks(page)).toEqual([TERMS, PRIVACY])
  await expect(page).toHaveURL(/\/$/) // still in the app

  const stored = await page.evaluate((key) => localStorage.getItem(key), `consent-storage:${TELEGRAM_ID}`)
  expect(JSON.parse(stored ?? 'null')).toMatchObject({ state: { noticeSeen: true } })
  expect(await page.evaluate(() => localStorage.getItem('consent-storage'))).toBeNull()

  // The next launch of the same account: no line
  await page.reload()
  await expect(page.getByRole('navigation').first()).toBeVisible()
  await expect(page.getByTestId('consent-notice')).toHaveCount(0)

  // Another Telegram account opens the app on the same phone: its own first launch
  const other = api.addAccount(makeUser({ id: 88, telegram_id: '888000888', name: 'Bek', phone: '+998908888888', role: 'customer', store_id: null }))
  await relaunchAs(page, String(other.telegram_id), 'Bek')
  await page.reload()
  await expect(page.getByTestId('consent-notice')).toHaveText(NOTICE)

  // ... and the first account, back again, still has none
  await relaunchAs(page, TELEGRAM_ID)
  await page.reload()
  await expect(page.getByRole('navigation').first()).toBeVisible()
  await expect(page.getByTestId('consent-notice')).toHaveCount(0)
})

test('the consent line can be closed', async ({ page, api }) => {
  void api // the fixture launches the page inside (fake) Telegram
  await page.goto('/')
  const notice = page.getByTestId('consent-notice')
  await notice.getByRole('button', { name: 'Yopish' }).click()
  await expect(notice).toHaveCount(0)
})

test('staff never see the consent line', async ({ page, api }) => {
  api.signInAs('manager')
  await page.goto('/')
  await expect(page).toHaveURL(/\/manager$/)
  await expect(page.getByRole('navigation').first()).toBeVisible()
  await expect(page.getByTestId('consent-notice')).toHaveCount(0)
  expect(await page.evaluate((key) => localStorage.getItem(key), `consent-storage:${TELEGRAM_ID}`)).toBeNull()
})

test('the profile keeps the documents one tap away', async ({ page, api }) => {
  void api
  await page.goto('/profile')
  const documents = page.getByRole('region', { name: 'Hujjatlar' })
  await documents.scrollIntoViewIfNeeded()
  await expect(documents.getByRole('link', { name: 'Foydalanish shartlari' })).toHaveAttribute('href', TERMS)
  await expect(documents.getByRole('link', { name: 'Maxfiylik siyosati' })).toHaveAttribute('href', PRIVACY)

  await recordOpenLink(page)
  await documents.getByRole('link', { name: 'Maxfiylik siyosati' }).click()
  await documents.getByRole('link', { name: 'Foydalanish shartlari' }).click()
  expect(await openedLinks(page)).toEqual([PRIVACY, TERMS])
  await expect(page).toHaveURL(/\/profile$/)
})
