import { expect, openSignedIn, test } from './fixtures'

// P6-31: the owner builds a combo from the menu's dishes (slots, options, a price delta, the default), previews how the
// receipt reads (one combo line, its parts without prices, the fiscal lines) and picks the store's combo fiscal mode.

test('the owner builds a combo, previews its receipt and picks the fiscal mode', async ({ page, api, signIn, reverb }) => {
  void reverb
  const combo = api.menu.addProduct({ name: 'Lavash kombo', categoryId: 2, variants: [{ name: 'Kombo', price: '45000.00' }] })
  const lavash = api.menu.addProduct({ name: 'Lavash', categoryId: 2, variants: [{ name: 'Katta', price: '32000.00' }] })
  const pepsi = api.menu.addProduct({ name: 'Pepsi', categoryId: 2, variants: [{ name: '0,4 l', price: '8000.00' }] })
  const ayran = api.menu.addProduct({ name: 'Ayran', categoryId: 2, variants: [{ name: '0,5 l', price: '6000.00' }] })
  await openSignedIn(page, signIn, '/menu/combos')
  await expect(page.getByRole('heading', { name: 'Kombo', level: 1 })).toBeVisible()
  await expect(page.getByText("Hali kombo yo'q")).toBeVisible()

  await page.getByRole('button', { name: "Kombo qo'shish" }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Kombo mahsuloti').selectOption({ label: 'Lavash kombo' })
  // The first slot: the lavash
  await dialog.getByLabel('Slot nomi').fill('Asosiy')
  await dialog.getByLabel('Taom').selectOption({ label: 'Lavash · Katta' })
  // The second slot: the drink, ayran for 2 000 more
  await dialog.getByRole('button', { name: "Slot qo'shish" }).click()
  await dialog.getByLabel('Slot nomi').nth(1).fill('Ichimlik')
  await dialog.getByLabel('Taom').nth(1).selectOption({ label: 'Pepsi · 0,4 l' })
  await dialog.getByRole('button', { name: "Tanlov qo'shish" }).nth(1).click()
  await dialog.getByLabel('Taom').nth(2).selectOption({ label: 'Ayran · 0,5 l' })
  await dialog.getByLabel('Narx farqi').nth(2).fill('2000')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()

  await expect(page.getByText('Kombo saqlandi.')).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/combos')[0].body).toEqual({
    product_id: combo.id,
    slots: [
      { name: 'Asosiy', min_picks: 1, max_picks: 1, allow_modifiers: false, options: [{ variant_id: lavash.variants[0]!.id, quantity: 1, price_delta: '0', modifiers: [], is_default: true, label: null }] },
      {
        name: 'Ichimlik',
        min_picks: 1,
        max_picks: 1,
        allow_modifiers: false,
        options: [
          { variant_id: pepsi.variants[0]!.id, quantity: 1, price_delta: '0', modifiers: [], is_default: true, label: null },
          { variant_id: ayran.variants[0]!.id, quantity: 1, price_delta: '2000', modifiers: [], is_default: false, label: null },
        ],
      },
    ],
  })
  await expect(page.locator('p', { hasText: /^Lavash kombo$/ })).toBeVisible()

  // The receipt: one combo line with its parts under it, and the fiscal lines; ayran picked, the price follows
  await page.getByRole('button', { name: /^Chek ko'rinishi/ }).click()
  const preview = page.getByRole('dialog')
  const guest = preview.getByRole('region', { name: 'Mijoz cheki' })
  await expect(guest).toContainText('Lavash kombo')
  await expect(guest).toContainText('Asosiy — Lavash (Katta)')
  await expect(guest).toContainText('Ichimlik — Pepsi (0,4 l)')
  await preview.getByLabel('Ichimlik').selectOption(api.combos.combos[0]!.slots[1]!.options[1]!.uuid)
  await expect(guest).toContainText('Ichimlik — Ayran (0,5 l)')
  await expect(guest).toContainText('47')
  await preview.getByRole('button', { name: 'Oynani yopish' }).last().click()

  // The fiscal mode: by parts
  await page.getByLabel('Kombo fiskal chekda').selectOption('split')
  await expect(page.getByText('Fiskal chek rejimi saqlandi.')).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/fiscal/settings')[0].body).toEqual({ fiscal_settings: { combo_fiscal_mode: 'split' } })
  expect(api.unhandled).toEqual([])
})

test('the panel checks the slots, and a combo is switched off and deleted', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.menu.addProduct({ name: 'Burger kombo', categoryId: 2, variants: [{ name: 'Kombo', price: '52000.00' }] })
  await openSignedIn(page, signIn, '/menu/combos')
  await page.getByRole('button', { name: "Kombo qo'shish" }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Kombo mahsuloti').selectOption({ label: 'Burger kombo' })
  await dialog.getByLabel('Slot nomi').fill('Asosiy')
  await dialog.getByLabel('Taom').selectOption({ label: 'Osh · 1 porsiya' })
  await dialog.getByLabel('Narx farqi').fill('-5')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  // The panel's own check first
  await expect(dialog.getByText(/Har slotga nom/)).toBeVisible()
  await dialog.getByLabel('Narx farqi').fill('0')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Kombo saqlandi.')).toBeVisible()

  await page.getByRole('switch', { name: /Sotuvda: Burger kombo/ }).click()
  await expect(page.getByText("O'chirilgan")).toBeVisible()
  expect(api.callsTo('PUT', /\/v1\/owner\/combos\/\d+/)[0].body).toEqual({ is_active: false })

  await page.getByRole('button', { name: /^O'chirish/ }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: "O'chirish" }).click()
  await expect(page.getByText("Hali kombo yo'q")).toBeVisible()
  expect(api.unhandled).toEqual([])
})
