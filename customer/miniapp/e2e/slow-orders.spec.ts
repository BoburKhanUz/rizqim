import { addLavashToCart } from './cartFlow'
import { expect, test } from './fixtures'

// On a slow network the orders page (its own chunk) arrives late. The router navigates in a React transition, so the
// confirmation stays on screen until then: the tap on "Buyurtmani kuzatish" must still show at once that it worked.
test('"Buyurtmani kuzatish" shows that it is opening while the orders page is still downloading', async ({ page, api }) => {
  let release: () => void = () => {}
  const released = new Promise<void>((resolve) => {
    release = resolve
  })
  await page.route(/\/assets\/Orders-[^/]+\.js$/, async (route) => {
    await released
    await route.continue()
  })

  await addLavashToCart(page)
  await page.getByRole('button', { name: /Olib ketish/ }).click()
  await expect(page.getByText('Chilonzor filiali')).toBeVisible()
  await page.getByRole('button', { name: /^Buyurtma berish/ }).click()
  await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()

  const track = page.getByRole('link', { name: 'Buyurtmani kuzatish' })
  await track.click()
  await expect(track).toHaveAttribute('aria-busy', 'true', { timeout: 1000 })

  release()
  await expect(page.getByRole('region', { name: 'Faol buyurtmalar' }).getByText(`#${api.orders[0].short_code}`, { exact: true })).toBeVisible()
})
