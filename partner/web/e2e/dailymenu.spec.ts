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
  expect(api.callsTo('PUT', '/v1/owner/daily-menu/rotation')[0].body).toEqual({ branch_id: 1, product_ids: [osh.id] })
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

test('a kompleks is made of dishes, switched off and deleted', async ({ page, api, signIn, reverb }) => {
  void reverb
  const salad = api.menu.addProduct({ name: 'Salat', categoryId: 2, variants: [{ name: 'porsiya', price: '8000.00' }] })
  const complex = api.menu.addProduct({ name: 'Tushlik kompleks', categoryId: 2, variants: [{ name: 'porsiya', price: '32000.00' }] })
  await openSignedIn(page, signIn, '/daily-menu')
  await page.getByRole('tab', { name: 'Komplekslar' }).click()
  await expect(page.getByText("Komplekslar yo'q")).toBeVisible()

  await page.getByRole('button', { name: "Kompleks qo'shish" }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Kompleks mahsuloti').selectOption(String(complex.id))
  await dialog.getByLabel('Taom', { exact: true }).selectOption(String(api.menu.products.find((product) => product.name === 'Osh')!.variants[0].id))
  await dialog.getByLabel('Nomi').fill('Birinchi')
  await dialog.getByRole('button', { name: "Taom qo'shish" }).click()
  await dialog.getByLabel('Taom', { exact: true }).nth(1).selectOption(String(salad.variants[0].id))
  await dialog.getByRole('button', { name: 'Saqlash' }).click()

  const card = page.getByRole('listitem').filter({ hasText: 'Tushlik kompleks' })
  await expect(card).toBeVisible()
  await expect(card.getByText('Birinchi')).toBeVisible()
  await expect(card.getByText(/Salat/)).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/meal-sets')[0].body).toMatchObject({ product_id: complex.id, parts: [{ quantity: 1, label: 'Birinchi' }, { quantity: 1, label: null }] })

  // Switched off: the kitchen stops preparing it as a set
  await card.getByRole('switch', { name: 'Kompleks yoqilgan' }).click()
  await expect(card.getByText("O'chirilgan")).toBeVisible()
  expect(api.callsTo('PUT', /\/v1\/owner\/meal-sets\/\d+$/)[0].body).toEqual({ is_active: false })

  await card.getByRole('button', { name: /O'chirish/ }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: "O'chirish" }).click()
  await expect(page.getByText("Komplekslar yo'q")).toBeVisible()
  expect(api.menu.mealSets).toHaveLength(0)
})

test('the yield report shows what each batch made, sold, wrote off and left', async ({ page, api, signIn, reverb }) => {
  void reverb
  const day = '2026-11-14'
  api.menu.yieldRows = {
    batches: [
      { id: 41, uuid: 'b-41', business_date: day, product_id: 7, product_name: 'Osh', product_variant_id: null, variant_name: null, unit: 'piece', label: '1-qozon', started_at: `${day}T06:00:00Z`, closed_at: null, is_open: true, produced: 40, sold: 30, written_off: 4, left: 3, oversold: 0, variance: 3, sold_percent: '75.0', written_off_percent: '10.0' },
    ],
    days: [{ business_date: day, product_id: 7, product_name: 'Osh', unit: 'piece', batches: 1, produced: 40, sold: 30, written_off: 4, left: 3, variance: 3, sold_percent: '75.0', written_off_percent: '10.0' }],
    write_offs: [{ reason: 'unsold', label: 'Sotilmay qoldi', count: 2, quantity: 4 }],
    in_sets: [{ business_date: day, product_id: 9, name: 'Mastava', unit: 'piece', quantity: 12 }],
  }
  api.menu.writeOffRows = [
    { uuid: 'w-1', batch_id: 41, product_id: 7, product_name: 'Osh', variant_name: null, quantity: 4, unit: 'piece', reason: 'unsold', reason_label: 'Sotilmay qoldi', note: 'Kechki ortiqcha', business_date: day, shift_id: null, written_off_by: { id: 5, name: 'Kassir Ali' }, created_at: `${day}T15:00:00Z` },
  ]
  await openSignedIn(page, signIn, '/yield')
  await page.getByLabel('Qaysi kundan').fill(day)
  await page.getByLabel('Qaysi kungacha').fill(day)

  const days = page.getByRole('table', { name: "Kunlar bo'yicha" })
  await expect(days.getByText('Osh')).toBeVisible()
  await expect(days.getByText('75,0%')).toBeVisible()
  const batches = page.getByRole('table', { name: 'Partiyalar' })
  await expect(batches.getByText('1-qozon')).toBeVisible()
  await expect(batches.getByText('+3')).toBeVisible()
  await expect(page.getByText('Kompleks ichida sotilgan')).toBeVisible()
  await expect(page.getByText(/Mastava/)).toBeVisible()
  expect(api.callsTo('GET', '/v1/owner/batch-yield').at(-1)!.query).toMatchObject({ branch_id: '1', from: day, to: day })

  await page.getByRole('tab', { name: 'Hisobdan chiqarilganlar' }).click()
  const writeOffs = page.getByRole('table', { name: 'Hisobdan chiqarilganlar' })
  await expect(writeOffs.getByText('Kechki ortiqcha')).toBeVisible()
  await expect(writeOffs.getByText('Kassir Ali')).toBeVisible()
})
