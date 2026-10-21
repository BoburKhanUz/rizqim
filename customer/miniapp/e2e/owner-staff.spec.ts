import { expect, test } from './fixtures'

// P2-25: the owner's staff screen (login phone, PIN), the branches' working hours and the read-only parts of a dish

test.describe('staff', () => {
  test('shows the state of each login phone and sets a PIN', async ({ page, api }) => {
    api.signInAs('owner')
    await page.goto('/admin/employees')

    await expect(page.getByText('Jami 3 ta xodim')).toBeVisible()
    await expect(page.getByText('Menejer • Chilonzor filiali')).toBeVisible()
    await expect(page.getByText('Oshpaz • Chilonzor filiali')).toBeVisible()
    await expect(page.getByText('Taklif kutilmoqda')).toBeVisible()

    await page.getByRole('button', { name: 'Aziza Oshpaz: boshqarish' }).click()
    await expect(page.getByText('SMS bilan kirish yoqilgan')).toBeVisible()
    await page.getByRole('button', { name: "PIN o'rnatish: Aziza Oshpaz" }).click()
    const dialog = page.getByRole('dialog')
    await dialog.getByLabel('Yangi PIN kod').fill('1234')
    await dialog.getByRole('button', { name: 'Saqlash' }).click()
    await expect(dialog.getByRole('alert')).toHaveText('PIN juda oddiy: boshqasini tanlang.')

    await dialog.getByLabel('Yangi PIN kod').fill('4821')
    await dialog.getByRole('button', { name: 'Saqlash' }).click()
    await expect(dialog).toBeHidden()
    expect(api.callsTo('PUT', '/owner/staff/12/pin').at(-1)?.body).toEqual({ pin: '4821' })
    await expect(page.getByText("PIN o'rnatilgan").first()).toBeVisible()

    // reset
    await page.getByRole('button', { name: "PIN ni o'chirish: Aziza Oshpaz" }).click()
    await page.getByRole('alertdialog').getByRole('button', { name: "PIN ni o'chirish" }).click()
    await expect.poll(() => api.callsTo('DELETE', '/owner/staff/12/pin').length).toBe(1)
  })

  test('refuses to change a phone the cook proved by SMS, in the words of the backend; changes a written one', async ({ page, api }) => {
    api.signInAs('owner')
    await page.goto('/admin/employees')

    await page.getByRole('button', { name: 'Aziza Oshpaz: boshqarish' }).click()
    await page.getByRole('button', { name: "Raqamni o'zgartirish: Aziza Oshpaz" }).click()
    let dialog = page.getByRole('dialog')
    await dialog.getByLabel(/Telefon/).fill('+998909998877')
    await dialog.getByRole('button', { name: 'Saqlash' }).click()
    await expect(dialog.getByRole('alert')).toContainText('xodimning o\'zi SMS kod bilan tasdiqlagan')
    await dialog.getByRole('button', { name: 'Bekor qilish' }).click()

    await page.getByRole('button', { name: 'Sardor Kuryer: boshqarish' }).click()
    await page.getByRole('button', { name: "Raqam qo'shish: Sardor Kuryer" }).click()
    dialog = page.getByRole('dialog')
    await dialog.getByLabel(/Telefon/).fill('+998905554433')
    await dialog.getByRole('button', { name: 'Saqlash' }).click()
    await expect(dialog).toBeHidden()
    expect(api.callsTo('PATCH', '/owner/staff/13').at(-1)?.body).toEqual({ phone: '+998905554433' })
  })

  test('adds a cook with a login phone and shows the invite link', async ({ page, api }) => {
    api.signInAs('owner')
    await page.goto('/admin/employees')

    await page.getByLabel('Lavozim').selectOption('cook')
    await page.getByLabel('Ism-familiya').fill('Dilnoza')
    await page.getByLabel(/Kirish telefoni/).fill('+998903332211')
    await page.getByRole('button', { name: "Xodim qo'shish" }).click()

    await expect(page.getByRole('button', { name: 'Nusxa olish' })).toBeVisible()
    expect(api.callsTo('POST', '/owner/staff')[0].body).toEqual({ name: 'Dilnoza', role: 'cook', branch_id: 1, phone: '+998903332211' })
  })
})

test('the owner changes the working hours of a branch', async ({ page, api }) => {
  api.signInAs('owner')
  await page.goto('/admin/branches')

  await expect(page.getByText('Yunusobod filiali')).toBeVisible()
  await page.getByRole('button', { name: 'Yunusobod filiali: ish vaqtini tahrirlash' }).click()
  await page.getByLabel('Ochilish vaqti').fill('08:00')
  await page.getByLabel('Yopilish vaqti').fill('23:30')
  await page.getByRole('button', { name: 'Saqlash' }).click()

  await expect(page.getByText('08:00 – 23:30')).toBeVisible()
  expect(api.callsTo('PATCH', '/owner/branches/2')[0].body).toEqual({ open_time: '08:00', close_time: '23:30', status: 'active' })
})

test('a dish shows its unit, weight and modifiers read only, with a pointer to the web panel', async ({ page, api }) => {
  api.signInAs('owner')
  await page.goto('/admin/catalog/edit/4')
  await expect(page.getByLabel('Faqat ko\'rish')).toContainText('Kilogramm')
  await expect(page.getByLabel('Faqat ko\'rish')).toContainText(/Qadam: 0,5\skg/)
  await expect(page.getByText('Web panelda tahrirlash')).toBeVisible()

  await page.goto('/admin/catalog/edit/3')
  await expect(page.getByRole('heading', { name: 'Sous', level: 3 })).toBeVisible()
  await expect(page.getByText('Majburiy')).toBeVisible()
  await expect(page.getByText('Pishloqli')).toBeVisible()
  await expect(page.getByText("Qo'shimchalar", { exact: true })).toBeVisible()
})
