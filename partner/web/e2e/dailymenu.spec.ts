import { expect, openSignedIn, test } from './fixtures'

// P6-15: the daily menu. The manager ticks the dishes cooked today and saves; a dish of the rotation is on sale only on a
// day it is ticked. The yield report opens for the same branch.

test('the dishes of the day are ticked and saved for the branch', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/daily-menu')
  await expect(page.getByRole('heading', { name: 'Bugungi menyu', level: 1 })).toBeVisible()
  // No menu yet: every dish is on sale, and the page says so
  await expect(page.getByText(/menyu belgilanmagan/)).toBeVisible()
  // The dishes are permanent until they are made daily
  await expect(page.getByText('Doimiy taomlar')).toBeVisible()

  await page.getByRole('button', { name: /Kunlik qilish Osh/ }).click()
  await expect(page.getByRole('checkbox', { name: 'Osh' })).toBeChecked()
  await page.getByRole('button', { name: 'Saqlash' }).click()

  await expect(page.getByText('Menyu saqlandi.')).toBeVisible()
  const osh = api.menu.products.find((product) => product.name === 'Osh')!
  expect(api.callsTo('PUT', '/v1/owner/daily-menu/rotation')[0].body).toEqual({ product_ids: [osh.id] })
  expect(api.callsTo('PUT', '/v1/owner/daily-menu')[0].body).toMatchObject({ branch_id: 1, product_ids: [osh.id] })
  await expect(page.getByText(/menyu belgilanmagan/)).toHaveCount(0)
})

test('the yield report opens for the chosen days', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/yield')
  await expect(page.getByRole('heading', { name: 'Hosil hisoboti', level: 1 })).toBeVisible()
  await expect(page.getByText("Bu davrda partiya yo'q")).toBeVisible()
  expect(api.callsTo('GET', '/v1/owner/batch-yield')[0].query).toMatchObject({ branch_id: '1' })
})
