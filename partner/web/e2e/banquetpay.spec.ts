import { expect, openSignedIn, OWNER, test } from './fixtures'
import { BANQUET_EVENT } from './fake/banquetpay'

// P6-53: the event's payment schedule (the overdue interim, a bank transfer against it), the cancellation with the
// computed parts and a manager's override, the "Tadbirlar" report with its Excel file and the payment rules.

test('the schedule shows the overdue row and a bank transfer pays it', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, `/banquets/schedule?event=${BANQUET_EVENT}`)
  const rows = page.getByRole('table', { name: "To'lov qatorlari" })
  await expect(rows).toContainText('Zaklad')
  await expect(page.getByText(/1 ta to'lov muddati o'tgan/)).toBeVisible()

  await page.getByRole('button', { name: "Oraliq to'lov uchun bank o'tkazmasini yozish" }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel("To'lov topshirig'i raqami").fill('PP-145')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText("Bank o'tkazmasi yozildi.")).toBeVisible()
  await expect(rows).toContainText('PP-145')
  await expect(page.getByText(/to'lov muddati o'tgan/)).toHaveCount(0)
  const call = api.callsTo('POST', `/v1/pos/banquets/${BANQUET_EVENT}/payments/eeeeeeee-0000-4000-8000-0000000000a2/bank-transfer`)[0]
  expect(call.body).toEqual({ reference: 'PP-145' })
})

test('the event is cancelled with an overridden retained part', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, `/banquets/schedule?event=${BANQUET_EVENT}`)
  await page.getByRole('button', { name: 'Tadbirni bekor qilish' }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.getByText('Tadbirgacha 20 kun: qoida — 30% ushlab qolinadi')).toBeVisible()
  await expect(dialog.getByTestId('bqp-quote')).toContainText('kassadan naqd')
  await dialog.getByLabel("Ushlab qolinadigan summani o'zgartirish").check()
  await dialog.getByRole('textbox', { name: 'Ushlab qolinadigan summa' }).fill('500000')
  await dialog.getByLabel("O'zgartirish sababi").fill('Ega rozi')
  await dialog.getByLabel('Bekor qilish sababi').fill("Mijoz to'yni ko'chirdi")
  await dialog.getByRole('button', { name: 'Ha, bekor qilish' }).click()
  await expect(page.getByText('Tadbir bekor qilindi.')).toBeVisible()
  await expect(page.getByText(/Bekor qilingan: to'langan/)).toBeVisible()
  await expect(page.getByRole('button', { name: 'Tadbirni bekor qilish' })).toHaveCount(0)
  expect(api.callsTo('POST', `/v1/pos/banquets/${BANQUET_EVENT}/cancellation`)[0].body).toEqual({ reason: "Mijoz to'yni ko'chirdi", retained: '500000', override_reason: 'Ega rozi' })
})

test('the report, its Excel file and the payment rules', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/banquets/report')
  await expect(page.getByRole('heading', { name: 'Tadbirlar hisoboti', level: 1 })).toBeVisible()
  const table = page.getByRole('table', { name: 'Tadbirlar' })
  await expect(table).toContainText("Karimovlar to'yi")
  await expect(table).toContainText('+998901234567')

  const [file] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'Excel yuklab olish' }).click()])
  expect(file.suggestedFilename()).toMatch(/^tadbirlar-\d{4}-\d{2}-\d{2}-\d{4}-\d{2}-\d{2}\.xlsx$/)

  await page.getByLabel('Eslatma kunlari (vergul bilan)').fill('5, 2')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Qoidalar saqlandi.')).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/pos/banquets/payment-settings')[0].body).toMatchObject({ reminder_days: [5, 2], version: 0 })

  await table.getByRole('link', { name: "To'lov jadvali" }).click()
  await expect(page).toHaveURL(new RegExp(`/banquets/schedule\\?event=${BANQUET_EVENT}$`))
  await expect(page.getByRole('table', { name: "To'lov qatorlari" })).toBeVisible()
})

test('a reports-only reader sees phones masked and no rules', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/banquets/report', { ...OWNER, id: 9, name: 'Dilshod', role: 'manager', phone: '+998901112233', permissions: ['reports.view'] })
  await expect(page.getByRole('table', { name: 'Tadbirlar' })).toContainText('+99890*****67')
  await expect(page.getByText("To'lov va bekor qilish qoidalari")).toHaveCount(0)
})
