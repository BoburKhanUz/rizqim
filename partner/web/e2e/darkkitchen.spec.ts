import { expect, openSignedIn, test } from './fixtures'

// P6-55 dark kitchen: the owner adds a brand and hides it on Telegram, moves a dish under a brand, stops "Tovuq" on the
// branch ("Tovuq tugadi") and reopens it, reads the channel net revenue report, saves the aggregator commission and
// downloads the Excel file.

test('brands: add one, its channels, a dish under it', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/brands')
  await expect(page.getByRole('heading', { name: 'Brendlar', level: 1 })).toBeVisible()
  await expect(page.getByRole('heading', { name: /Lavash Lab/ })).toBeVisible()
  await expect(page.getByText(/Narx: Agregator \+15%/)).toBeVisible()

  await page.getByRole('button', { name: "Brend qo'shish" }).click()
  let dialog = page.getByRole('dialog')
  await dialog.getByLabel('Nomi').fill('Pizza Pro')
  await dialog.getByLabel('Qisqa kod').fill('pp')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Brend saqlandi.')).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/brands')[0].body).toMatchObject({ name: 'Pizza Pro', short_code: 'PP' })

  await page.getByRole('listitem').filter({ hasText: 'Pizza Pro' }).getByRole('button', { name: 'Kanallar va narx' }).click()
  dialog = page.getByRole('dialog')
  await dialog.getByRole('switch', { name: 'Telegramda sotiladi' }).click()
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Kanallar saqlandi.')).toBeVisible()
  await expect(page.getByText(/Sotilmaydi: Telegram/)).toBeVisible()

  await page.getByRole('checkbox', { name: 'Osh ni tanlash' }).check()
  await page.getByLabel('Qaysi brendga').selectOption({ label: 'Lavash Lab' })
  await page.getByRole('button', { name: "O'tkazish (1)" }).click()
  await expect(page.getByText("1 ta taom o'tkazildi.")).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/product-brands')[0].body).toEqual({ brand_id: 2, product_ids: [501] })
})

test('"Tovuq tugadi": stop the ingredient on the branch and reopen it', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/stoplist/ingredients')
  await expect(page.getByRole('heading', { name: 'Ingredientlar', level: 1 })).toBeVisible()
  const table = page.getByRole('table', { name: 'Ingredientlar' })
  await expect(table).toContainText('Tovuq')

  await table.getByRole('button', { name: 'Tovuq tugadi' }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog).toContainText('Osh')
  await dialog.getByLabel('Sabab').fill('Yetkazib beruvchi kelmadi')
  await dialog.getByRole('button', { name: "To'xtatish" }).click()
  await expect(page.getByText("Tovuq to'xtatildi: 1 ta taom yopildi.")).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/ingredients/1/stop')[0].body).toEqual({ branch_id: 1, reason: 'Yetkazib beruvchi kelmadi' })
  await expect(table).toContainText('Yetkazib beruvchi kelmadi')

  await table.getByRole('button', { name: 'Qayta ochish' }).click()
  await expect(page.getByText('Tovuq qayta ochildi: taomlar sotuvga qaytdi.')).toBeVisible()
  expect(api.callsTo('DELETE', '/v1/owner/ingredients/1/stop')).toHaveLength(1)
})

test('the channel net revenue report, the commissions and the Excel file', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/reports/channels')
  await expect(page.getByRole('heading', { name: "Kanal bo'yicha sof tushum", level: 1 })).toBeVisible()
  const table = page.getByRole('table', { name: 'Kanal va brend' })
  await expect(table).toContainText('Agregator · 25%')
  await expect(table).toContainText('Lavash Lab')
  await expect(table.getByRole('row').last()).toContainText('Jami')

  await page.getByRole('button', { name: 'Komissiyalar' }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.getByLabel('Agregator: foiz')).toHaveValue('25')
  await dialog.getByLabel('Agregator: foiz').fill('20')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Komissiyalar saqlandi.')).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/channel-commissions')[0].body).toEqual({ rates: [{ channel: 'aggregator', percent: '20', note: 'Yandex' }] })

  const [file] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'Excel yuklab olish' }).click()])
  expect(file.suggestedFilename()).toMatch(/^kanal-tushum-\d{4}-\d{2}-\d{2}-\d{4}-\d{2}-\d{2}\.xlsx$/)
})
