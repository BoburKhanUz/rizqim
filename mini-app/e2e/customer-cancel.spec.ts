import { expect, test } from './fixtures'

test('customer cancels a new unpaid order with a note and sees the reason', async ({ page, api }) => {
  const cancellable = api.addOrder({ status: 'new', payment_status: 'pending' })
  const accepted = api.addOrder({ status: 'accepted', payment_status: 'pending' })
  const code = (uuid: string) => `#${uuid.slice(0, 8).toUpperCase()}`

  await page.goto('/orders')
  const active = page.getByRole('region', { name: 'Faol buyurtmalar' })
  await expect(active.getByText(code(cancellable.uuid))).toBeVisible()
  await expect(active.getByText(code(accepted.uuid))).toBeVisible()

  await active.getByText(code(cancellable.uuid)).click() // opens the details
  await active.getByRole('button', { name: /Bekor qilish/ }).click()

  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Izoh (ixtiyoriy)').fill('Fikrimni o\'zgartirdim')
  await dialog.getByRole('button', { name: 'Bekor qilish', exact: true }).click()
  await expect(dialog).toBeHidden()

  const [request] = api.callsTo('POST', `/orders/${cancellable.uuid}/cancel`)
  expect(request.body).toEqual({ reason: 'customer_request', note: 'Fikrimni o\'zgartirdim' })

  // The order left the active ones and is now in the history, with the reason and the note
  const history = page.getByRole('region', { name: 'Buyurtmalar tarixi' })
  await expect(history.getByText(code(cancellable.uuid))).toBeVisible()
  await expect(history.getByText('Bekor qilindi')).toBeVisible()
  await expect(history.getByText('Sizning so\'rovingizga ko\'ra')).toBeVisible()
  await expect(history.getByText('"Fikrimni o\'zgartirdim"')).toBeVisible()
  await expect(active.getByText(code(cancellable.uuid))).toHaveCount(0)
})

test('customer cannot cancel an order the store already accepted', async ({ page, api }) => {
  const accepted = api.addOrder({ status: 'accepted', payment_status: 'pending' })

  await page.goto('/orders')
  const active = page.getByRole('region', { name: 'Faol buyurtmalar' })
  await active.getByText(`#${accepted.uuid.slice(0, 8).toUpperCase()}`).click()

  // The details are open (the complaint link is there), yet there is no way to cancel
  await expect(active.getByRole('link', { name: /Shikoyat qilish/ })).toBeVisible()
  await expect(active.getByRole('button', { name: /Bekor qilish/ })).toHaveCount(0)
})
