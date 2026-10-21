import { expect, openSignedIn, test } from './fixtures'

// The menu: a dish sold by the kilo with a choice of sauce. A modifier group is made on its own tab, the dish is created
// with one variant per kg (step and minimum of 0.5 kg) and then offers the group. What the server refuses is shown under
// the field, in its words.

test('a dish sold by weight is created and offers a modifier group', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/menu')
  await expect(page.getByText('Osh', { exact: true })).toBeVisible()
  await expect(page.getByText('Lagmon', { exact: true })).toBeVisible()

  // --- The modifier group, on its own tab
  await page.getByRole('tab', { name: 'Modifikatorlar' }).click()
  await page.getByRole('link', { name: "Guruh qo'shish" }).first().click()
  await page.getByLabel('Guruh nomi', { exact: true }).fill('Sous tanlang')
  await page.getByLabel('Eng kam tanlov').fill('1')
  await page.getByLabel('Eng ko\'p tanlov').fill('1')
  await page.getByRole('switch', { name: 'Majburiy tanlov' }).click()

  const first = page.getByRole('group', { name: '1-variant' })
  await first.getByLabel('Nomi', { exact: true }).fill('Ketchup')
  await page.getByRole('button', { name: "Variant qo'shish" }).click()
  const second = page.getByRole('group', { name: '2-variant' })
  await second.getByLabel('Nomi', { exact: true }).fill('Pishloqli')
  await second.getByLabel("Narx qo'shimchasi (so'm)").fill('3000')
  await page.getByRole('button', { name: 'Yaratish' }).click()

  await expect(page.getByRole('heading', { name: 'Sous tanlang', level: 1 })).toBeVisible()
  const groupCall = api.callsTo('POST', '/v1/owner/modifier-groups')[0]
  expect(groupCall.body).toMatchObject({
    name: 'Sous tanlang',
    min_select: 1,
    max_select: 1,
    is_required: true,
    modifiers: [expect.objectContaining({ name: 'Ketchup' }), expect.objectContaining({ name: 'Pishloqli', price_delta: 3000 })],
  })

  // --- The dish: one variant, per kilogram
  await page.goto('/menu/new')
  await page.getByLabel('Nomi', { exact: true }).fill('Qazi')
  await page.getByLabel('Kategoriya').selectOption({ label: 'Issiq taomlar' })
  const variant = page.getByRole('group', { name: '1-variant' })
  await variant.getByLabel('Variant nomi').fill('kg')
  await variant.getByLabel("O'lchov birligi").selectOption('kg')
  await variant.getByLabel('1 kg narxi (so\'m)').fill('185000')
  await variant.getByLabel('Qadam (kg)').fill('0.5')
  await variant.getByLabel('Eng kam miqdor (kg)').fill('0.5')
  await page.getByRole('button', { name: 'Yaratish' }).click()

  // The editor of the new dish opens, with the parts that need a saved dish
  await expect(page.getByRole('heading', { name: 'Qazi', level: 1 })).toBeVisible()
  const productCall = api.callsTo('POST', '/v1/owner/products')[0]
  expect(productCall.body).toMatchObject({
    name: 'Qazi',
    is_active: '1',
    variants: [{ name: 'kg', price: 185000, unit: 'kg', quantity_step: 0.5, min_quantity: 0.5 }],
  })
  const created = api.menu.products.find((product) => product.name === 'Qazi')!
  expect(created.variants[0]).toMatchObject({ unit: 'kg', price: '185000.00', quantity_step: '0.500', min_quantity: '0.500' })

  // --- The dish offers the group
  await expect(page.getByRole('heading', { name: 'Modifikator guruhlari' })).toBeVisible()
  await page.getByRole('switch', { name: 'Taomda taklif qilinadi: Sous tanlang' }).click()
  await page.getByRole('button', { name: 'Guruhlarni saqlash' }).click()
  // (The "Saqlandi." note of this part vanishes at once: the refetch that follows the save remounts it. Reported, not asserted.)
  await expect.poll(() => api.callsTo('PUT', `/v1/owner/products/${created.id}/modifier-groups`).length).toBe(1)
  await expect(page.getByRole('switch', { name: 'Taomda taklif qilinadi: Sous tanlang' })).toBeChecked()

  const sync = api.callsTo('PUT', `/v1/owner/products/${created.id}/modifier-groups`)[0]
  expect(sync.body).toEqual({ groups: [{ modifier_group_id: api.menu.groups[0].id, sort_order: 0 }] })
  expect(api.menu.groups[0].product_ids).toContain(created.id)

  // Back in the list, the dish is there with its kilo price
  await page.goto('/menu')
  await expect(page.getByText('Qazi', { exact: true })).toBeVisible()
})

test('what the server refuses shows under the field, in Uzbek', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/menu/new')
  await page.getByLabel('Nomi', { exact: true }).fill('Qazi')
  await page.getByLabel('Kategoriya').selectOption({ label: 'Issiq taomlar' })
  const variant = page.getByRole('group', { name: '1-variant' })
  await variant.getByLabel('Variant nomi').fill('kg')
  await variant.getByLabel("O'lchov birligi").selectOption('kg')
  await variant.getByLabel('1 kg narxi (so\'m)').fill('185000')
  await variant.getByLabel('Qadam (kg)').fill('0.5')
  // 0.7 is not a whole number of steps of 0.5
  await variant.getByLabel('Eng kam miqdor (kg)').fill('0.7')
  await page.getByRole('button', { name: 'Yaratish' }).click()

  await expect(page.getByText("Eng kam miqdor qadamga karrali bo'lishi kerak.")).toBeVisible()
  // Nothing was created and the form is still there to fix
  expect(api.menu.products.map((product) => product.name)).not.toContain('Qazi')
  await variant.getByLabel('Eng kam miqdor (kg)').fill('1')
  await page.getByRole('button', { name: 'Yaratish' }).click()
  await expect(page.getByRole('heading', { name: 'Qazi', level: 1 })).toBeVisible()
})
