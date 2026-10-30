import { expect, openSignedIn, test } from './fixtures'

// P6-33 (KDS v2): the owner gives the kitchen stations their time targets (the universal kinds, the expo station), a dish its
// own target, and reads the prep time report. The server works out every figure; the fake answers like it.

test('the owner sets station targets, an expo station and a dish of its own on the POS setup page', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.planLocked.delete('kds')
  api.format.overrides.kds_expo = true
  await openSignedIn(page, signIn, '/pos-setup')
  await page.getByRole('tab', { name: 'Sexlar' }).click()

  const stations = page.getByRole('table', { name: 'Oshxona sexlari' })
  await expect(stations.getByRole('row').nth(1)).toContainText('Grill')
  await expect(stations.getByRole('row').nth(1)).toContainText('5:00')

  // The drinks station's target: 2 minutes
  await page.getByRole('button', { name: 'Ichimliklar: sexni tahrirlash' }).click()
  await page.getByLabel('Vaqt maqsadi').fill('120')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Ichimliklar saqlandi.')).toBeVisible()
  expect(api.callsTo('PATCH', '/v1/owner/kitchen-stations/2')[0].body).toMatchObject({ target_seconds: 120 })
  await expect(stations.getByRole('row').nth(2)).toContainText('2:00')

  // An expo station: no dishes go to it, its target is the assembly time
  await page.getByRole('button', { name: "Sex qo'shish" }).click()
  await page.getByLabel('Nomi').fill("Yig'ish")
  await page.getByLabel('Turi').selectOption('expo')
  await expect(page.getByText(/Yig'ish sexiga taom biriktirilmaydi/)).toBeVisible()
  await page.getByLabel('Vaqt maqsadi').fill('90')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText("Yig'ish sexi qo'shildi.")).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/kitchen-stations')[0].body).toEqual({ branch_id: 1, name: "Yig'ish", kind: 'expo', is_active: true, show_on_kds: true, target_seconds: 90 })

  // A dish's own target over its station's, then taken off again
  await page.getByLabel('Taom', { exact: true }).selectOption({ label: 'Lagmon' })
  await page.getByLabel('Soniya').fill('420')
  await page.getByRole('button', { name: "Qo'shish", exact: true }).click()
  const dishes = page.getByRole('table', { name: "Taomning o'z vaqti" })
  await expect(dishes).toContainText('Lagmon')
  await expect(dishes).toContainText('7:00')
  expect(api.callsTo('PUT', '/v1/owner/kds/targets/products')[0].body).toMatchObject({ branch_id: 1, items: [{ target_seconds: 420 }] })
  await page.getByRole('button', { name: "Lagmon: o'z vaqtini olib tashlash" }).click()
  await expect(page.getByText("Lagmon: endi sex vaqti qo'llanadi.")).toBeVisible()
  await expect(page.getByText("Hech bir taomning o'z vaqti yo'q: sex vaqti qo'llanadi.")).toBeVisible()
  expect(api.unhandled).toEqual([])
})

test('without the module a station has no target field and the dishes panel is hidden', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/pos-setup')
  await page.getByRole('tab', { name: 'Sexlar' }).click()
  await page.getByRole('button', { name: "Sex qo'shish" }).click()
  await expect(page.getByText(/Vaqt maqsadi "Yig'ish ekrani va sex vaqtlari" moduli bilan ishlaydi/)).toBeVisible()
  await expect(page.getByLabel('Vaqt maqsadi')).toHaveCount(0)
  await expect(page.getByRole('heading', { name: "Taomning o'z vaqti" })).toHaveCount(0)
  expect(api.callsTo('GET', '/v1/owner/kds/targets')).toHaveLength(0)
})

test('the prep time report shows the figures per station, dish and hour and narrows to a station', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.planLocked.delete('kds')
  await openSignedIn(page, signIn, '/kitchen-times')

  await expect(page.getByRole('heading', { name: 'Tayyorlash vaqti', level: 1 })).toBeVisible()
  const stations = page.getByRole('table', { name: "Sexlar bo'yicha" })
  await expect(stations.getByRole('row').nth(1)).toContainText('Grill')
  await expect(stations.getByRole('row').nth(1)).toContainText('4:00')
  await expect(stations.getByRole('row').nth(1)).toContainText('1 / 3 (33,3%)')
  await expect(page.getByRole('table', { name: "Soatlar bo'yicha" })).toContainText('13:00–14:00')
  await expect(page.getByRole('heading', { name: "Yig'ish · Yig'ish" })).toBeVisible()
  expect(api.callsTo('GET', '/v1/owner/reports/kitchen-times')[0].query).toMatchObject({ branch_id: '1' })

  await page.getByLabel('Sex', { exact: true }).selectOption({ label: 'Grill' })
  await expect.poll(() => api.callsTo('GET', '/v1/owner/reports/kitchen-times').filter((call) => call.query.station_id === '1').length).toBeGreaterThan(0)
  await expect(page.getByRole('heading', { name: "Yig'ish · Yig'ish" })).toHaveCount(0)
  await expect(page.getByRole('table', { name: "Sexlar bo'yicha" }).getByRole('row')).toHaveCount(2)

  // The nav has the page
  await expect(page.getByRole('link', { name: 'Tayyorlash vaqti' })).toBeVisible()
})

test('a branch without the kitchen display gets the server refusal on the report', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/kitchen-times')
  await expect(page.getByText("Oshxona ekrani (KDS) bu filial formatida o'chirilgan.")).toBeVisible()
})
