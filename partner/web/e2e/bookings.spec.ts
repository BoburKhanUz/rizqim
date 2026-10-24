import { expect, openSignedIn, test } from './fixtures'

// Bronlar: the booking calendar of the rooms. A booking is made in a room, shown on the day line at its time in the store's
// zone, refused with the booking in the way when the room is taken, and canceled with a reason.

const DAY = '2026-12-05'

test('a booking is made, collides with another, and is canceled', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/bookings')
  await page.getByLabel('Sana').fill(DAY)
  const room = page.getByRole('group', { name: 'Xona 1' })
  await expect(room).toBeVisible()
  await expect(room.getByRole('button', { name: /Aziz/ })).toHaveCount(0)

  // A birthday in Xona 1, 19:00-23:00
  await page.getByRole('button', { name: "Xona 1: bron qo'shish" }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Ismi (yoki guruh nomi)').fill('Aziz Karimov')
  await dialog.getByLabel('Telefon').fill('90 123 45 67')
  await dialog.getByLabel('Mehmonlar soni').fill('30')
  await dialog.getByLabel('Tadbir turi').selectOption('tugilgan_kun')
  await dialog.getByLabel('Boshlanishi').fill('19:00')
  await dialog.getByLabel('Tugashi').fill('23:00')
  await dialog.getByRole('checkbox', { name: /Darhol tasdiqlash/ }).check()
  await dialog.getByRole('button', { name: 'Saqlash', exact: true }).click()

  await expect(page.getByText("Aziz Karimov uchun bron qo'shildi.")).toBeVisible()
  const chip = room.getByRole('button', { name: /Aziz Karimov, 19:00–23:00, Tasdiqlangan/ })
  await expect(chip).toBeVisible()
  await expect(chip).toContainText('30 kishi')
  expect(api.bookings.bookings[0]).toMatchObject({ dining_table_id: 11, contact_phone: '+998901234567', guests_planned: 30, status: 'confirmed', starts_at: '2026-12-05T14:00:00.000Z', ends_at: '2026-12-05T18:00:00.000Z' })
  expect(api.callsTo('POST', '/v1/pos/bookings')[0].body).toMatchObject({ branch_id: 1, starts_at: `${DAY}T19:00:00`, ends_at: `${DAY}T23:00:00`, confirm: true })

  // The same room at an overlapping time: refused, the booking in the way is named, the form is kept
  await page.getByRole('button', { name: "Xona 1: bron qo'shish" }).click()
  await dialog.getByLabel('Ismi (yoki guruh nomi)').fill('Dilnoza')
  await dialog.getByLabel('Boshlanishi').fill('21:00')
  await dialog.getByLabel('Tugashi').fill('23:30')
  await dialog.getByRole('button', { name: 'Saqlash', exact: true }).click()
  await expect(dialog.getByText('Xona shu vaqtda band.')).toBeVisible()
  await expect(dialog.getByText('19:00–23:00 (Tasdiqlangan)')).toBeVisible()
  await expect(dialog.getByLabel('Ismi (yoki guruh nomi)')).toHaveValue('Dilnoza')

  // Another room is free at that time
  await dialog.getByLabel('Xona', { exact: true }).selectOption({ label: 'Xona 2 (Asosiy zal)' })
  await dialog.getByRole('button', { name: 'Saqlash', exact: true }).click()
  await expect(page.getByText("Dilnoza uchun bron qo'shildi.")).toBeVisible()
  await expect(page.getByRole('group', { name: 'Xona 2' }).getByRole('button', { name: /Dilnoza, 21:00–23:30, So'rov/ })).toBeVisible()

  // Cancel the first one: a reason is needed
  await chip.click()
  const detail = page.getByRole('dialog')
  await detail.getByRole('button', { name: 'Bekor qilish' }).click()
  await detail.getByRole('button', { name: 'Bronni bekor qilish' }).click()
  await expect(detail.getByText('Bekor qilish sababini yozing.')).toBeVisible()
  await detail.getByLabel('Bekor qilish sababi', { exact: true }).fill('Mijoz bekor qildi')
  await detail.getByRole('button', { name: 'Bronni bekor qilish' }).click()
  await expect(page.getByText('Aziz Karimov broni bekor qilindi.')).toBeVisible()
  await expect(room.getByRole('button', { name: /Aziz/ })).toHaveCount(0)
  expect(api.bookings.bookings[0]).toMatchObject({ status: 'canceled', cancel_reason: 'Mijoz bekor qildi' })

  // With the canceled ones shown it is back, struck through in its state
  await page.getByRole('checkbox', { name: 'Bekor qilinganlar ham' }).check()
  await expect(room.getByRole('button', { name: /Aziz Karimov, 19:00–23:00, Bekor qilindi/ })).toBeVisible()
})

test('the confirmation is previewed and sent to the guest; with no channel the cashier is told so', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/bookings')
  await page.getByLabel('Sana').fill(DAY)
  await page.getByRole('button', { name: "Xona 1: bron qo'shish" }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Ismi (yoki guruh nomi)').fill('Aziz Karimov')
  await dialog.getByLabel('Telefon').fill('90 123 45 67')
  await dialog.getByLabel('Mehmonlar soni').fill('12')
  await dialog.getByLabel('Boshlanishi').fill('19:00')
  await dialog.getByLabel('Tugashi').fill('23:00')
  await dialog.getByRole('checkbox', { name: /Darhol tasdiqlash/ }).check()
  await dialog.getByRole('button', { name: 'Saqlash', exact: true }).click()
  await page.getByRole('group', { name: 'Xona 1' }).getByRole('button', { name: /Aziz Karimov, 19:00–23:00/ }).click()

  // The text the guest gets, with the room fee line
  const detail = page.getByRole('dialog')
  const section = detail.getByRole('region', { name: 'Tasdiq' })
  await section.getByRole('button', { name: "Tasdiqni ko'rish" }).click()
  await expect(section.getByText("Xona puli: 300 000 so'm (dam olish kuni narxi)")).toBeVisible()
  await expect(section.getByText('Yuborish mumkin: Telegram')).toBeVisible()

  // Sent by itself over Telegram, and the booking remembers when
  await section.getByRole('button', { name: 'Yuborish' }).click()
  await expect(section.getByText("Telegram'ga yuborildi.")).toBeVisible()
  await expect(section.getByText(/Tasdiq yuborilgan: \d{2}:\d{2}, Telegram/)).toBeVisible()
  expect(api.bookings.sent).toHaveLength(1)
  expect(api.callsTo('POST', '/v1/pos/bookings/' + api.bookings.bookings[0]!.uuid + '/confirmation/send')[0].body).toEqual({ channel: 'auto' })

  // Neither Telegram nor SMS: a clear message, nothing queued
  api.bookings.channels = { telegram: false, sms: false }
  await section.getByRole('button', { name: 'Tasdiqni yopish' }).click()
  await section.getByRole('button', { name: "Tasdiqni ko'rish" }).click()
  await expect(section.getByText(/Yuborish mumkin: yo'q/)).toBeVisible()
  await section.getByRole('button', { name: 'Yuborish' }).click()
  await expect(section.getByRole('alert')).toContainText("mehmon Telegram'da yo'q")
  expect(api.bookings.sent).toHaveLength(1)
})
