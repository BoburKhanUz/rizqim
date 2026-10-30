import { expect, openSignedIn, test } from './fixtures'

// P6-32: the owner's screens of the fast food / coffee shop wave — the modifier group's quick hints (where the options appear at
// the till, the preselected option) and the "Stiker va yorliq" tab of the POS setup page, which comes with the labels module.

test('a choyxona sees no sticker tab', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/pos-setup')
  await expect(page.getByRole('tab', { name: 'Format', selected: true })).toBeVisible()
  await expect(page.getByRole('tab', { name: 'Stiker va yorliq' })).toHaveCount(0)
})

test('the owner sets the sticker size and which categories get a sticker; only the changes are sent', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.overrides = { labels: true }
  await openSignedIn(page, signIn, '/pos-setup')

  await page.getByRole('tab', { name: 'Stiker va yorliq' }).click()
  await expect(page.getByLabel("Rulon o'lchami")).toHaveValue('40x30')
  await expect(page.getByLabel('Stiker chiqadigan taomlar')).toHaveValue('all')
  await expect(page.getByRole('checkbox', { name: /Har dona uchun alohida stiker/ })).toBeChecked()
  await expect(page.getByRole('button', { name: 'Saqlash' })).toBeDisabled()
  await expect(page.getByTestId('sticker-preview')).toContainText('Olib ketish')

  await page.getByLabel("Rulon o'lchami").selectOption('58x40')
  await page.getByLabel('Stiker chiqadigan taomlar').selectOption('categories')
  // Asking for a category is the form's job, before the server's
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Kamida bitta kategoriya tanlang.')).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/branches/1/label-settings')).toHaveLength(0)

  await page.getByRole('checkbox', { name: 'Ichimliklar' }).check()
  await page.getByRole('checkbox', { name: /Olib ketish paketiga yorliq/ }).uncheck()
  await page.getByRole('button', { name: 'Saqlash' }).click()

  await expect(page.getByText('Stiker sozlamalari saqlandi.')).toBeVisible()
  const sent = api.callsTo('PUT', '/v1/owner/branches/1/label-settings')[0].body as Record<string, unknown>
  expect(sent).toMatchObject({ size: '58x40', scope: 'categories', bag_label: false })
  expect(sent.category_ids).toHaveLength(1)
  expect(sent).not.toHaveProperty('per_unit')

  // The page shows what the server kept, also after a reload
  await page.reload()
  await page.getByRole('tab', { name: 'Stiker va yorliq' }).click()
  await expect(page.getByLabel("Rulon o'lchami")).toHaveValue('58x40')
  await expect(page.getByLabel('Stiker chiqadigan taomlar')).toHaveValue('categories')
  await expect(page.getByRole('checkbox', { name: 'Ichimliklar' })).toBeChecked()
  await expect(page.getByRole('checkbox', { name: /Olib ketish paketiga yorliq/ })).not.toBeChecked()
})

test('a size selector group with a default option is saved when the store has quick modifiers', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.overrides = { quick_modifiers: true }
  await openSignedIn(page, signIn, '/menu/groups/new')

  await page.getByLabel('Guruh nomi', { exact: true }).fill('Hajm')
  await page.getByLabel('Eng kam tanlov').fill('1')
  await page.getByLabel("Eng ko'p tanlov").fill('1')
  await page.getByRole('switch', { name: 'Majburiy tanlov' }).click()
  await page.getByLabel("Variantlar qayerda ko'rinadi").selectOption('size')

  const first = page.getByRole('group', { name: '1-variant' })
  await first.getByLabel('Nomi', { exact: true }).fill('Kichik')
  await page.getByRole('button', { name: "Variant qo'shish" }).click()
  const second = page.getByRole('group', { name: '2-variant' })
  await second.getByLabel('Nomi', { exact: true }).fill("O'rta")
  await second.getByLabel("Narx qo'shimchasi (so'm)").fill('5000')
  await second.getByRole('switch', { name: /Standart/ }).click()
  // One pick per cup: choosing the first as the default moves it off the second
  await first.getByRole('switch', { name: /Standart/ }).click()
  await expect(second.getByRole('switch', { name: /Standart/ })).not.toBeChecked()
  await second.getByRole('switch', { name: /Standart/ }).click()
  await page.getByRole('button', { name: 'Yaratish' }).click()

  await expect(page.getByRole('heading', { name: 'Hajm', level: 1 })).toBeVisible()
  const sent = api.callsTo('POST', '/v1/owner/modifier-groups')[0].body as { display_mode: string; modifiers: Array<{ name: string; is_default: boolean }> }
  expect(sent.display_mode).toBe('size')
  expect(sent.modifiers.map((option) => [option.name, option.is_default])).toEqual([['Kichik', false], ["O'rta", true]])
  expect(api.menu.groups[0].display_mode).toBe('size')
  await expect(page.getByLabel("Variantlar qayerda ko'rinadi")).toHaveValue('size')
  await expect(page.getByRole('group', { name: '2-variant' }).getByRole('switch', { name: /Standart/ })).toBeChecked()
})

test('the size selector needs a group that takes one option; a store without the module is refused by the server', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/menu/groups/new')

  await page.getByLabel('Guruh nomi', { exact: true }).fill('Hajm')
  await page.getByLabel("Variantlar qayerda ko'rinadi").selectOption('size')
  await page.getByRole('group', { name: '1-variant' }).getByLabel('Nomi', { exact: true }).fill('Kichik')
  await page.getByRole('button', { name: 'Yaratish' }).click()
  await expect(page.getByText("Hajm tanlash uchun «Eng ko'p tanlov» 1 bo'lishi kerak.")).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/modifier-groups')).toHaveLength(0)

  await page.getByLabel("Eng ko'p tanlov").fill('1')
  await page.getByRole('button', { name: 'Yaratish' }).click()
  await expect(page.getByText(/moduli bu filialda o'chiq/)).toBeVisible()
  expect(api.menu.groups).toHaveLength(0)
})
