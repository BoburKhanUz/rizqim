import { addLavashToCart } from './cartFlow'
import { expect, test } from './fixtures'

// Wave P (receipts): the seller before ordering (the restaurant page, the checkout) and the fiscal receipts on the order.
// SHOTS_DIR set: the screens are saved there too (390x844, the Telegram Mini App size).

const SHOTS = process.env.SHOTS_DIR
const SALE_URL = 'https://ofd.soliq.uz/epi?t=EP000000000025&r=121&c=20261124120000&s=800031554082'
const REFUND_URL = 'https://ofd.soliq.uz/epi?t=EP000000000025&r=123&c=20261124130000&s=900031555055'

test('the restaurant page and the checkout say who sells the food', async ({ page, api }) => {
  expect(api.seller?.legal_name).toBe('"Lazzat Burger" MChJ')
  await page.goto('/')
  await page.getByRole('heading', { name: 'Evos', level: 3 }).click()
  await expect(page.getByRole('heading', { name: 'Evos', level: 1 })).toBeVisible()

  const seller = page.getByTestId('store-seller')
  await expect(seller.locator('summary')).toContainText('Sotuvchi: "Lazzat Burger" MChJ')
  await seller.locator('summary').click()
  await expect(seller.getByText('305123456')).toBeVisible()
  await expect(seller.getByText("Toshkent sh., Chilonzor t., Bunyodkor ko'chasi 1")).toBeVisible()
  await expect(seller.getByText('+998712001020')).toBeVisible()
  await expect(seller.getByRole('link', { name: /Shikoyatlar/ })).toBeVisible()
  if (SHOTS) await page.screenshot({ path: `${SHOTS}/miniapp-restaurant-seller.png` })

  await addLavashToCart(page)
  const checkoutSeller = page.getByTestId('seller-info')
  await expect(checkoutSeller.getByText('"Lazzat Burger" MChJ')).toBeVisible()
  await expect(checkoutSeller.getByText(/@lazzat_support/)).toBeVisible()
  await checkoutSeller.scrollIntoViewIfNeeded()
  if (SHOTS) await page.screenshot({ path: `${SHOTS}/miniapp-checkout-seller.png` })
})

test('the order shows its fiscal and refund receipts and opens them on ofd.soliq.uz', async ({ page, api }) => {
  api.addOrder({
    status: 'refunded',
    payment_status: 'refunded',
    payment_method: 'payme',
    seller: api.seller,
    receipts: [
      { uuid: 's-1', type: 'sale', status: 'ready', provider: 'payme', url: SALE_URL, issued_at: '2026-11-24T12:00:00+05:00' },
      { uuid: 'r-1', type: 'refund', status: 'ready', provider: 'payme', url: REFUND_URL, issued_at: '2026-11-24T13:00:00+05:00' },
    ],
  })
  const pending = api.addOrder({
    status: 'delivered',
    payment_status: 'paid',
    payment_method: 'click',
    receipts: [{ uuid: null, type: 'sale', status: 'pending', provider: 'click', url: null, issued_at: null }],
  })
  await page.goto('/orders')
  const history = page.getByRole('region', { name: 'Buyurtmalar tarixi' })
  await history.getByText(`#${api.orders.find((o) => o.status === 'refunded')!.short_code}`, { exact: true }).click()

  const sale = page.getByRole('button', { name: 'Fiskal chek' })
  const refund = page.getByRole('button', { name: 'Qaytarish cheki' })
  await expect(sale).toBeVisible()
  await expect(refund).toBeVisible()
  await expect(page.getByTestId('seller-info').getByText('"Lazzat Burger" MChJ')).toBeVisible()
  await refund.scrollIntoViewIfNeeded()
  if (SHOTS) await page.screenshot({ path: `${SHOTS}/miniapp-order-receipts.png` })

  // Telegram opens it outside the Mini App (WebApp.openLink): record what it is asked to open
  await page.evaluate(() => {
    const opened: string[] = []
    ;(window as unknown as { __opened: string[] }).__opened = opened
    const webApp = (window as unknown as { Telegram: { WebApp: { openLink: (url: string) => void } } }).Telegram.WebApp
    webApp.openLink = (url: string) => opened.push(url)
  })
  await sale.click()
  await refund.click()
  expect(await page.evaluate(() => (window as unknown as { __opened: string[] }).__opened)).toEqual([SALE_URL, REFUND_URL])

  await history.getByText(`#${pending.short_code}`, { exact: true }).click()
  await expect(page.getByText('Chek tayyorlanmoqda')).toBeVisible()
})
