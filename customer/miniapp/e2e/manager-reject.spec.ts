import { expect, test } from './fixtures'

test('manager rejects a new order with a reason', async ({ page, api }) => {
  api.signInAs('manager')
  const fresh = api.addOrder({ status: 'new', short_code: 'N100' })
  const accepted = api.addOrder({ status: 'accepted', short_code: 'A200' })

  await page.goto('/manager')
  await expect(page.getByRole('button', { name: /^Faol \(2\)$/ })).toBeVisible()
  await expect(page.getByText('#N100')).toBeVisible()
  await expect(page.getByText('#A200')).toBeVisible()

  await page.getByRole('button', { name: 'Rad etish' }).click()

  const dialog = page.getByRole('dialog', { name: /Buyurtmani rad etish/ })
  const confirm = dialog.getByRole('button', { name: 'Rad etish', exact: true })
  await expect(confirm).toBeDisabled() // a reason is required
  // "Mijoz kelmadi" only makes sense for a pickup order that was accepted, "Kuryer yo'q" only for a delivery order
  await expect(dialog.getByRole('radio', { name: 'Mijoz kelmadi' })).toHaveCount(0)
  await expect(dialog.getByRole('radio', { name: "Kuryer yo'q" })).toHaveCount(0)
  await expect(dialog.getByRole('radio')).toHaveCount(4)

  await dialog.getByRole('radio', { name: 'Mahsulot qolmagan' }).check()
  await dialog.getByLabel('Izoh (ixtiyoriy)').fill('Lavash tugadi')
  await confirm.click()
  await expect(dialog).toBeHidden()

  const [request] = api.callsTo('POST', `/staff/orders/${fresh.uuid}/status`)
  expect(request.body).toEqual({ status: 'rejected', reason: 'out_of_stock', note: 'Lavash tugadi' })

  // It left the active list; the accepted one stays
  await expect(page.getByRole('button', { name: /^Faol \(1\)$/ })).toBeVisible()
  await expect(page.getByText('#N100')).toHaveCount(0)
  await expect(page.getByText('#A200')).toBeVisible()
  expect(api.callsTo('POST', `/staff/orders/${accepted.uuid}/status`)).toHaveLength(0)

  // ... and is in the finished ones with its reason
  await page.getByRole('button', { name: /^Yakunlangan/ }).click()
  await expect(page.getByText('#N100')).toBeVisible()
  await expect(page.getByText('Rad etildi')).toBeVisible()
  await expect(page.getByText('Mahsulot qolmagan')).toBeVisible()
  await expect(page.getByText('"Lavash tugadi"')).toBeVisible()
})
