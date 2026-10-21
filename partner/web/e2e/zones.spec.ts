import { expect, openSignedIn, test } from './fixtures'

// Delivery zones: a polygon drawn with clicks on the map (the tiles are a stub picture served by the fake backend) and saved
// with its prices. The point order in the request is GeoJSON's: longitude first, the ring closed.

test('a delivery zone is drawn on the map and saved', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/zones')
  await expect(page.getByText("Hudud yo'q")).toBeVisible()

  await page.getByRole('button', { name: 'Yangi hudud' }).first().click()
  const map = page.getByTestId('zone-map')
  await expect(map.locator('.leaflet-container')).toBeVisible()

  // Saving without a shape asks for one
  await page.getByLabel('Nomi', { exact: true }).fill('Markaz')
  await page.getByRole('button', { name: 'Hududni saqlash' }).click()
  await expect(page.getByText('Hududni xaritada chizing (kamida 3 ta nuqta).').first()).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/delivery-zones')).toHaveLength(0)

  // Four clicks make the corners of the shape; the first point closes it
  const box = (await map.boundingBox())!
  const corners: Array<[number, number]> = [
    [0.3, 0.3],
    [0.7, 0.3],
    [0.7, 0.7],
    [0.3, 0.7],
  ]
  for (const [x, y] of corners) await page.mouse.click(box.x + box.width * x, box.y + box.height * y)
  await expect(page.getByText('Shakllar: 1 · nuqtalar: 4')).toBeVisible()
  await page.getByRole('button', { name: 'Shaklni yopish' }).click()

  await page.getByLabel('Yetkazish narxi').fill('12000')
  await page.getByLabel('Eng kam buyurtma').fill('30000')
  await page.getByLabel('Taxminiy vaqt').fill('40')
  await page.getByRole('button', { name: 'Hududni saqlash' }).click()

  await expect(page.getByText("Markaz hududi qo'shildi.")).toBeVisible()
  const request = api.callsTo('POST', '/v1/owner/delivery-zones')[0].body as { name: string; polygon: { type: string; coordinates: number[][][] }; delivery_price: number; min_order_amount: number; eta_minutes: number }
  expect(request).toMatchObject({ name: 'Markaz', delivery_price: 12000, min_order_amount: 30000, eta_minutes: 40, polygon: { type: 'Polygon' } })
  const ring = request.polygon.coordinates[0]
  expect(ring).toHaveLength(5)
  expect(ring[0]).toEqual(ring[4])
  // [longitude, latitude] around Tashkent
  for (const [lng, lat] of ring) {
    expect(lng).toBeGreaterThan(69)
    expect(lng).toBeLessThan(70)
    expect(lat).toBeGreaterThan(41)
    expect(lat).toBeLessThan(42)
  }
  expect(api.zones.zones).toHaveLength(1)

  // The zone is in the list with its terms
  await expect(page.getByRole('button', { name: 'Markaz hududini ochish' })).toContainText('12')
})

test('a ready shape can be pasted as GeoJSON, and a broken one is refused', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/zones')
  await page.getByRole('button', { name: 'Yangi hudud' }).first().click()
  await page.getByLabel('Nomi', { exact: true }).fill('Shimol')
  await page.locator('summary', { hasText: 'GeoJSON' }).click()

  await page.getByLabel('GeoJSON', { exact: true }).fill('{"type":"Point","coordinates":[69.2,41.3]}')
  await page.getByRole('button', { name: "Xaritaga qo'llash" }).click()
  await expect(page.getByText("GeoJSON noto'g'ri. Polygon yoki MultiPolygon bo'lsin.")).toBeVisible()

  const triangle = { type: 'Polygon', coordinates: [[[69.2, 41.3], [69.3, 41.3], [69.25, 41.4], [69.2, 41.3]]] }
  await page.getByLabel('GeoJSON', { exact: true }).fill(JSON.stringify(triangle))
  await page.getByRole('button', { name: "Xaritaga qo'llash" }).click()
  await expect(page.getByText('Shakllar: 1 · nuqtalar: 3')).toBeVisible()

  await page.getByRole('button', { name: 'Hududni saqlash' }).click()
  await expect(page.getByText("Shimol hududi qo'shildi.")).toBeVisible()
  expect(api.zones.zones[0].polygon).toEqual(triangle)
  expect(api.zones.zones[0].area_km2).toMatch(/^\d+\.\d{2}$/)
})
