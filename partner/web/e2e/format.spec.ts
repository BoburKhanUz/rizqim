import { expect, openSignedIn, test } from './fixtures'

// P6-11: the branch's format (POS sozlamalari, first tab). The owner picks a profile, switches modules over its defaults and
// adds the sample stations and dishes; the plan's and the law's locks come from the server and cannot be overridden.

test('the format page shows the profile, the modules with their locks and the template', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/pos-setup')

  await expect(page.getByRole('tab', { name: 'Format', selected: true })).toBeVisible()
  const group = page.getByRole('radiogroup', { name: 'Formatni tanlang' })
  await expect(group.getByRole('radio', { name: /Choyxona/ })).toHaveAttribute('aria-checked', 'true')
  await expect(group.getByRole('radio', { name: /Stolovaya/ })).toContainText('Xizmat haqi 0% (qonun)')
  await expect(group.getByRole('radio', { name: /Osh markazi/ })).toContainText('Tavsiya: Start tarifi')
  await expect(page.getByText('Kassa ochilganda: zal xaritasi')).toBeVisible()

  // The plan has no KDS: off, cannot be switched on, and says which plan has it
  const kds = page.getByRole('switch', { name: 'Oshxona ekrani (KDS): yoqilgan' })
  await expect(kds).not.toBeChecked()
  await expect(kds).toBeDisabled()
  await expect(page.getByText('Asosiy tarifda bor')).toBeVisible()
  await expect(page.getByRole('switch', { name: 'Xonalar va tapchanlar: yoqilgan' })).toBeChecked()

  await expect(page.getByRole('region', { name: 'Sexlar' })).toContainText('Osh va qozon (Osh va qozon)')
  expect(api.callsTo('GET', '/v1/owner/pos/format')[0].query).toMatchObject({ branch_id: '1' })
})

test('a module is switched on and put back to the format default', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/pos-setup')

  await page.getByRole('switch', { name: 'Buyurtma raqami: yoqilgan' }).click()
  await expect(page.getByText('Modullar saqlandi.')).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/pos/format')[0].body).toEqual({ branch_id: 1, modules: { queue_numbers: true } })
  await expect(page.getByRole('switch', { name: 'Buyurtma raqami: yoqilgan' })).toBeChecked()

  await page.getByRole('button', { name: 'Buyurtma raqami: formatnikiga qaytarish' }).click()
  await expect(page.getByRole('switch', { name: 'Buyurtma raqami: yoqilgan' })).not.toBeChecked()
  expect(api.callsTo('PUT', '/v1/owner/pos/format')[1].body).toEqual({ branch_id: 1, modules: { queue_numbers: null } })
})

test('the profile is changed only after the owner confirms, and the first screen follows it', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/pos-setup')

  await page.getByRole('radio', { name: /Osh markazi/ }).click()
  const dialog = page.getByRole('alertdialog')
  await expect(dialog).toContainText('«Osh markazi» formatiga')
  // Nothing is sent until the answer
  expect(api.callsTo('PUT', '/v1/owner/pos/format')).toHaveLength(0)
  await dialog.getByRole('button', { name: "O'zgartirish" }).click()

  await expect(page.getByText("Format o'zgartirildi: Osh markazi.")).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/pos/format')[0].body).toEqual({ branch_id: 1, profile: 'osh_markazi' })
  await expect(page.getByRole('radio', { name: /Osh markazi/ })).toHaveAttribute('aria-checked', 'true')
  await expect(page.getByText('Kassa ochilganda: tez kassa')).toBeVisible()
  await expect(page.getByRole('switch', { name: 'Tez kassa: yoqilgan' })).toBeChecked()
})

test('switching to a stolovaya warns that the service charge becomes 0% when a charge is set', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.serviceFees = [{ branch_id: 1, hall_id: null, service_charge_percent: '10.00', service_charge_channels: null, place_fee_amount: '0.00', place_fee_mode: 'per_person', place_fee_channels: null }]
  await openSignedIn(page, signIn, '/pos-setup')

  // A profile that keeps the charge: the question has no warning
  await page.getByRole('radio', { name: /Osh markazi/ }).click()
  await expect(page.getByRole('alertdialog')).toBeVisible()
  await expect(page.getByRole('alertdialog').getByRole('note')).toHaveCount(0)
  await page.getByRole('alertdialog').getByRole('button', { name: 'Bekor qilish' }).click()
  await expect(page.getByRole('alertdialog')).toHaveCount(0)

  await page.getByRole('radio', { name: /Stolovaya/ }).click()
  await expect(page.getByRole('alertdialog').getByRole('note')).toContainText("Xizmat haqi 0% bo'ladi")
  await page.getByRole('alertdialog').getByRole('button', { name: "O'zgartirish" }).click()

  await expect(page.getByText("Format o'zgartirildi: Stolovaya (oshxona-liniya).")).toBeVisible()
  // The law's lock: the service charge module is off and cannot be switched on
  const charge = page.getByRole('switch', { name: 'Xizmat haqi: yoqilgan' })
  await expect(charge).not.toBeChecked()
  await expect(charge).toBeDisabled()
  await expect(page.getByText("Qonun bo'yicha o'chirilgan")).toBeVisible()
})

test('a stolovaya with no service charge set changes without the warning', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/pos-setup')
  await page.getByRole('radio', { name: /Stolovaya/ }).click()
  await expect(page.getByRole('alertdialog')).toContainText('«Stolovaya (oshxona-liniya)» formatiga')
  await expect(page.getByRole('alertdialog').getByRole('note')).toHaveCount(0)
})

test('the sample stations and dishes are added once and never over existing ones', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/pos-setup')

  await expect(page.getByRole('region', { name: 'Taomlar' })).toContainText('Osh — 1 kg')
  await page.getByRole('button', { name: "Namunani qo'shish" }).click()
  await expect(page.getByText("Qo'shildi: 0 ta sex, 1 ta taom.")).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/pos/format/template')[0].body).toEqual({ branch_id: 1 })

  // Everything is there now: the button is off, nothing more to add
  await expect(page.getByRole('button', { name: "Namunani qo'shish" })).toBeDisabled()
  await expect(page.getByRole('region', { name: 'Taomlar' }).getByText('(bor)')).toBeAttached()
})
