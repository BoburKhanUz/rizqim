import { expect, openSignedIn, test } from './fixtures'

// The floor map: a place is dragged and resized on the grid with the mouse and the whole map is saved with one PUT. A room
// tariff is made on its own tab. The grid is 40 x 24 cells, so a pointer travel of N cell widths is N cells.

const GRID = { cols: 40, rows: 24 }

test('a place is dragged and resized on the floor map, then the layout is saved', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/floor')
  await page.getByRole('tab', { name: 'Zal xaritasi' }).click()
  const canvas = page.getByTestId('floor-canvas')
  await expect(canvas).toBeVisible()
  const place = page.getByTestId('place-11')
  await expect(place).toHaveAttribute('data-rect', '2,1,5,4')

  const box = (await canvas.boundingBox())!
  const cell = { w: box.width / GRID.cols, h: box.height / GRID.rows }

  // Drag by the body: three cells to the right and two down
  const body = (await place.boundingBox())!
  const startX = body.x + body.width / 2 - 10
  const startY = body.y + body.height / 2
  await page.mouse.move(startX, startY)
  await page.mouse.down()
  await page.mouse.move(startX + cell.w * 1.5, startY + cell.h, { steps: 4 })
  await page.mouse.move(startX + cell.w * 3, startY + cell.h * 2, { steps: 4 })
  await page.mouse.up()
  await expect(place).toHaveAttribute('data-rect', '5,3,5,4')

  // Resize by the corner: two cells wider, one taller
  const handle = (await page.getByTestId('resize-11').boundingBox())!
  const cornerX = handle.x + handle.width / 2
  const cornerY = handle.y + handle.height / 2
  await page.mouse.move(cornerX, cornerY)
  await page.mouse.down()
  await page.mouse.move(cornerX + cell.w * 2, cornerY + cell.h, { steps: 6 })
  await page.mouse.up()
  await expect(place).toHaveAttribute('data-rect', '5,3,7,5')

  // The place cannot be dragged out of the map
  const moved = (await place.boundingBox())!
  await page.mouse.move(moved.x + 20, moved.y + 20)
  await page.mouse.down()
  await page.mouse.move(box.x - 300, box.y - 300, { steps: 6 })
  await page.mouse.up()
  await expect(place).toHaveAttribute('data-rect', '0,0,7,5')

  // Back to a fitting spot with the numbers, then save
  await page.getByLabel('X (chapdan)').fill('5')
  await page.getByLabel('Y (yuqoridan)').fill('3')
  await expect(place).toHaveAttribute('data-rect', '5,3,7,5')
  await expect(page.getByText("1 ta joy o'zgardi, saqlanmagan")).toBeVisible()
  await page.getByRole('button', { name: 'Saqlash', exact: true }).click()
  await expect(page.getByText('Zal xaritasi saqlandi.')).toBeVisible()

  const request = api.callsTo('PUT', '/v1/owner/dining-tables/layout')[0]
  expect(request.body).toEqual({ branch_id: 1, tables: [{ id: 11, hall_id: 1, layout: { x: 5, y: 3, w: 7, h: 5 } }] })
  expect(api.floor.tables.find((table) => table.id === 11)?.layout).toEqual({ x: '5.00', y: '3.00', w: '7.00', h: '5.00' })

  // The saved map is what a fresh visit shows
  await page.reload()
  await page.getByRole('tab', { name: 'Zal xaritasi' }).click()
  await expect(page.getByTestId('place-11')).toHaveAttribute('data-rect', '5,3,7,5')
  await expect(page.getByTestId('place-12')).toHaveAttribute('data-rect', '10,1,5,4')
})

test('a room tariff is made for a kind of place and for the weekend', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/floor')
  await page.getByRole('tab', { name: 'Xona narxi' }).click()
  await expect(page.getByText("Narx yo'q")).toBeVisible()

  await page.getByRole('button', { name: "Narx qo'shish" }).first().click()
  // The price is needed
  await page.getByRole('button', { name: 'Saqlash', exact: true }).click()
  await expect(page.getByText('Narxni kiriting (0 — bepul xona).')).toBeVisible()
  await page.getByRole('dialog').getByLabel('Kun turi').selectOption('weekend')
  await page.getByRole('dialog').getByLabel('Narxi', { exact: true }).fill('450000')
  await page.getByRole('button', { name: 'Saqlash', exact: true }).click()

  await expect(page.getByText("Narx qo'shildi.")).toBeVisible()
  const table = page.getByRole('table', { name: 'Xona narxi' })
  await expect(table.getByText('Barcha xonalar · barcha zallar')).toBeVisible()
  await expect(table.getByText('Dam olish kuni')).toBeVisible()
  await expect(table.getByText(/450\s000\sso'm/)).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/room-tariffs')[0].body).toEqual({
    branch_id: 1,
    dining_table_id: null,
    table_kind: 'room',
    hall_id: null,
    day_type: 'weekend',
    price: 450000,
    valid_from: null,
    valid_to: null,
  })
  expect(api.floor.tariffs).toHaveLength(1)

  // One room of its own, free in November
  await page.getByRole('button', { name: "Narx qo'shish" }).click()
  await page.getByRole('dialog').getByLabel('Narx nimaga').selectOption('room')
  await page.getByRole('dialog').getByLabel('Xona', { exact: true }).selectOption({ label: 'Xona 2 (Asosiy zal)' })
  await page.getByRole('dialog').getByLabel('Narxi', { exact: true }).fill('0')
  await page.getByRole('dialog').getByLabel('Boshlanish sanasi').fill('2026-11-01')
  await page.getByRole('dialog').getByLabel('Tugash sanasi').fill('2026-11-30')
  await page.getByRole('button', { name: 'Saqlash', exact: true }).click()
  await expect(table.getByText('Bepul')).toBeVisible()
  await expect(table.getByText('Xona 2')).toBeVisible()
  expect(api.floor.tariffs[1]).toMatchObject({ dining_table_id: 12, price: '0.00', valid_from: '2026-11-01', valid_to: '2026-11-30' })

  // The calendar
  await page.getByRole('checkbox', { name: 'Juma' }).check()
  await page.getByRole('button', { name: 'Kalendarni saqlash' }).click()
  await expect(page.getByText('Kalendar saqlandi.')).toBeVisible()
  expect(api.floor.calendar.weekend_days).toEqual([5, 6, 7])
})
