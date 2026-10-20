// Steps shared by the specs that start from a filled cart
import type { Locator, Page } from '@playwright/test'
import { expect } from './fixtures'

// The row of one product on the store page: the list item that holds both its name and its "add" button
export const productCard = (page: Page, name: string): Locator =>
  page
    .getByRole('listitem')
    .filter({ has: page.getByRole('heading', { name, exact: true }) })
    .filter({ has: page.getByRole('button', { name: /^Qo'shish/ }) })

// Browse the catalog: Home -> the store -> add a product -> the cart
export async function addLavashToCart(page: Page) {
  await page.goto('/')
  await page.getByRole('heading', { name: 'Evos', level: 3 }).click()
  await expect(page.getByRole('heading', { name: 'Evos', level: 1 })).toBeVisible()

  await productCard(page, 'Lavash').getByRole('button', { name: /^Qo'shish/ }).click()
  // The floating "view cart" bar appears as soon as something is in the cart (it once did not re-render on add)
  await page.getByRole('link', { name: /Savatni ko'rish/ }).click()

  await expect(page.getByRole('heading', { name: 'Savat', level: 1 })).toBeVisible()
  await expect(page.getByText('Lavash (Standart)')).toBeVisible()
}
