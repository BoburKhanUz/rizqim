import { expect, openSignedIn, test } from './fixtures'

// P6 stage B: Kassa sozlamalari → Lokal tarmoq. The owner sees which till is the branch's hub (the server's default: the
// first cashier till), picks another one and goes back to the automatic choice, and changes the branch's LAN key only after
// confirming it.

test('the owner picks the hub till, then goes back to the automatic one', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/pos-setup')
  await page.getByRole('tab', { name: 'Lokal tarmoq' }).click()

  await expect(page.getByTestId('lan-hub')).toContainText('Hozir: Kassa 1')
  await expect(page.getByTestId('lan-hub')).toContainText('Avtomatik')
  const picker = page.getByLabel('Markaz')
  await expect(picker).toHaveValue('')
  await expect(picker.locator('option')).toHaveText([/^Avtomatik/, /^Kassa 1 · Kassa · windows · /, "Kassa 2 · ilova noma'lum · android · hali ko'rinmagan"])
  await expect(page.getByLabel('Port')).toHaveValue('18480')
  await expect(page.getByRole('button', { name: 'Saqlash' })).toBeDisabled()
  expect(api.callsTo('GET', '/v1/owner/pos/lan')[0].query).toMatchObject({ branch_id: '1' })

  await picker.selectOption('till-2')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Lokal tarmoq sozlamalari saqlandi.')).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/pos/lan')[0].body).toEqual({ branch_id: 1, hub_device_id: 'till-2' })
  await expect(page.getByTestId('lan-hub')).toContainText('Hozir: Kassa 2')
  await expect(page.getByTestId('lan-hub')).toContainText('Siz tanlagansiz')

  await picker.selectOption('')
  await page.getByLabel('Port').fill('18500')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByTestId('lan-hub')).toContainText('Hozir: Kassa 1')
  expect(api.callsTo('PUT', '/v1/owner/pos/lan')[1].body).toEqual({ branch_id: 1, hub_device_id: null, port: 18500 })
  await expect(page.getByLabel('Port')).toHaveValue('18500')
})

test('the LAN key is changed only after the owner confirms', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/pos-setup')
  await page.getByRole('tab', { name: 'Lokal tarmoq' }).click()
  await expect(page.getByTestId('lan-key')).toContainText('Kalit versiyasi: 1 · Hali almashtirilmagan')

  await page.getByRole('button', { name: 'Kalitni almashtirish' }).click()
  const dialog = page.getByRole('alertdialog')
  await expect(dialog).toContainText('Kalit almashtirilsinmi?')
  await dialog.getByRole('button', { name: 'Bekor qilish' }).click()
  await expect(dialog).toHaveCount(0)
  expect(api.callsTo('POST', '/v1/owner/pos/lan/rotate')).toHaveLength(0)

  await page.getByRole('button', { name: 'Kalitni almashtirish' }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Almashtirish' }).click()
  await expect(page.getByText('Kalit almashtirildi: qurilmalar uni keyingi kirishda oladi.')).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/pos/lan/rotate')[0].body).toEqual({ branch_id: 1 })
  await expect(page.getByTestId('lan-key')).toContainText('Kalit versiyasi: 2 · Oxirgi almashtirilgan:')
})
