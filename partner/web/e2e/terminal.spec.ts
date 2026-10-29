import { expect, openSignedIn, test } from './fixtures'

// P6-16: "Karta terminali" (Fiskal, last tab). The owner says how the branch takes cards, ticks the installation list, marks a
// till as a smart kassa, and enters the bank terminal's day total of a closed shift; the server compares it with the Z.

const OPEN_TAB = async (page: import('@playwright/test').Page) => {
  await page.getByRole('tab', { name: 'Karta terminali' }).click()
  await expect(page.getByRole('form', { name: 'Chilonzor' })).toBeVisible()
}

test('the card mode, the checklist and the threshold are saved as what changed', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/fiscal')
  await OPEN_TAB(page)

  const branch = page.getByRole('form', { name: 'Chilonzor' })
  await expect(branch.getByText("O'rnatish tugallanmagan")).toBeVisible()
  await branch.getByLabel('Karta qanday qabul qilinadi').selectOption('terminal_only')
  await branch.getByRole('checkbox', { name: /Eski kassa sotuvda ishlatilmaydi/ }).check()
  await branch.getByLabel("Farq chegarasi (so'm)").fill('1000')
  await branch.getByRole('button', { name: 'Saqlash' }).click()

  await expect(branch.getByText('Saqlandi.')).toBeVisible()
  // Only the changed parts went out; ticking an item records who and when
  expect(api.callsTo('PUT', '/v1/owner/branches/1/terminal-flow')[0].body).toEqual({ card_mode: 'terminal_only', checklist: { old_kassa_unused: true }, difference_threshold: '1000' })
  await expect(branch.getByText(/Olim Egamov/)).toBeVisible()
  await expect(branch.getByText("O'rnatish tugallanmagan")).toBeVisible()

  // The rest of the list completes the installation
  await branch.getByRole('checkbox', { name: /Bank terminali rejimi sozlangan/ }).check()
  await branch.getByRole('checkbox', { name: /2D skaner bor/ }).check()
  await branch.getByRole('button', { name: 'Saqlash' }).click()
  await expect(branch.getByText("O'rnatish tayyor")).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/branches/1/terminal-flow')[1].body).toEqual({ checklist: { bank_terminal_mode: true, scanner_2d: true } })
})

test('a threshold that is not an amount is refused before anything is sent', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/fiscal')
  await OPEN_TAB(page)

  const branch = page.getByRole('form', { name: 'Chilonzor' })
  await branch.getByLabel("Farq chegarasi (so'm)").fill('ko‘p')
  await branch.getByRole('button', { name: 'Saqlash' }).click()
  await expect(branch.getByText("Chegarani so'mda kiriting (masalan 1000).")).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/branches/1/terminal-flow')).toHaveLength(0)
})

test('a branch whose format has the flow off is told where to switch it on', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.terminal.moduleEnabled = false
  await openSignedIn(page, signIn, '/fiscal')
  await page.getByRole('tab', { name: 'Karta terminali' }).click()
  await expect(page.getByText(/Bu filial formatida bank terminali oqimi o'chiq/)).toBeVisible()
  await expect(page.getByRole('form', { name: 'Chilonzor' })).toHaveCount(0)
})

test('a till is marked as a smart kassa with its provider kassa id', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/fiscal')
  await OPEN_TAB(page)

  const till = page.getByRole('form', { name: 'Kassa 1: smart-kassa' })
  await expect(till.getByLabel('Provayder kassa raqami')).toBeDisabled()
  await till.getByRole('checkbox', { name: /Smart-kassa/ }).check()
  await till.getByLabel('Provayder kassa raqami').fill('RHMT-1')
  await till.getByRole('button', { name: 'Saqlash' }).click()

  await expect(till.getByText('Provayderda ulangan')).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/fiscal/devices/dev-1')[0].body).toEqual({ smart_kassa: true, fiscal_kassa_id: 'RHMT-1' })
})

const SHIFT = 's0000000-0000-4000-8000-000000000012'

test('the bank terminal total of a closed shift is entered, compared with the Z and shown on the shift', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/fiscal')
  await OPEN_TAB(page)

  await expect(page.getByText("Hali hisobot yo'q")).toBeVisible()
  const form = page.getByRole('form', { name: 'Bank terminali summasini kiritish' })

  // Nothing is sent without a shift, nor with a total that is not an amount
  await form.getByRole('button', { name: 'Solishtirish' }).click()
  await expect(form.getByText('Smenani tanlang.')).toBeVisible()
  await form.getByLabel('Smena').selectOption(SHIFT)
  await form.getByLabel("Terminal kunlik jami (so'm)").fill('ko‘p')
  await form.getByRole('button', { name: 'Solishtirish' }).click()
  await expect(form.getByText("Summani so'mda kiriting (masalan 1250000).")).toBeVisible()
  expect(api.callsTo('PUT', /bank-reports/)).toHaveLength(0)

  // The terminal says 50 000 more than Rizqim's card money: a mismatch, and the owner is told it was reported
  await form.getByLabel("Terminal kunlik jami (so'm)").fill('1 250 000')
  await form.getByRole('button', { name: 'Solishtirish' }).click()
  await expect(form.getByText(/Saqlandi\. Farq .*50.000.* egaga xabar yuborildi\./)).toBeVisible()
  expect(api.callsTo('PUT', `/v1/owner/fiscal/bank-reports/${SHIFT}`)[0].body).toEqual({ source: 'manual', bank_total: '1250000' })
  const table = page.getByRole('table', { name: 'Bank terminali va Z' })
  await expect(table.getByText('Z #12')).toBeVisible()
  await expect(table.getByText('Mos emas')).toBeVisible()

  // The same shift is corrected from its row, the terminal's rows pasted: RRN matched, the totals agree
  await table.getByRole('button', { name: /hisobotini tuzatish/ }).click()
  await expect(form.getByLabel('Smena')).toHaveValue(SHIFT)
  await form.getByLabel('Qanday kiritiladi').selectOption('import')
  await form.getByLabel('Terminal tranzaksiyalari').fill('000123;700000\n000124;500000')
  await form.getByRole('button', { name: 'Solishtirish' }).click()
  await expect(form.getByText(/Saqlandi\. Farq: 0/)).toBeVisible()
  expect(api.callsTo('PUT', `/v1/owner/fiscal/bank-reports/${SHIFT}`)[1].body).toEqual({
    source: 'import',
    transactions: [
      { rrn: '000123', amount: '700000' },
      { rrn: '000124', amount: '500000' },
    ],
  })
  await expect(table.getByText('Mos', { exact: true })).toBeVisible()
  await expect(table.getByText('Tranzaksiyalar importi')).toBeVisible()
  // One row for the shift, replaced
  await expect(table.getByRole('row')).toHaveCount(2)

  // The mismatch filter asks the server for the shifts over the threshold only
  await page.getByRole('checkbox', { name: 'Faqat farq bor smenalar' }).check()
  await expect(page.getByText("Hali hisobot yo'q")).toBeVisible()
  expect(api.callsTo('GET', '/v1/owner/fiscal/bank-reports').some((call) => ['1', 'true'].includes(call.query.over_threshold))).toBe(true)

  // The Z of that shift carries the bank terminal's row
  await page.goto('/shifts')
  await page.getByRole('button', { name: 'Hisobotni ochish: 2026-10-21, Z 12' }).click()
  await expect(page.getByRole('article', { name: 'Z-hisobot № 12' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Bank terminali va Z' })).toBeVisible()
  await expect(page.getByText('Tranzaksiyalar importi')).toBeVisible()
})
