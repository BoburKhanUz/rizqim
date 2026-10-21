import { expect, test } from './fixtures'

// P2-25: the stop-list and the batches ("qozon") on the phone

test.describe('stop-list', () => {
  test('a manager stops a dish with one tap, finds it by search and puts it back', async ({ page, api }) => {
    api.signInAs('manager')
    await page.goto('/admin/stoplist')

    await expect(page.getByRole('heading', { name: 'Stop-list va qozon', level: 1 })).toBeVisible()
    const lavash = page.getByRole('switch', { name: 'Sotuvda: Lavash' })
    await expect(lavash).toHaveAttribute('aria-checked', 'true')

    await lavash.click()
    await expect(lavash).toHaveAttribute('aria-checked', 'false')
    expect(api.callsTo('POST', '/owner/stop-list')[0].body).toEqual({ type: 'product', id: 1 })
    expect(api.staff.stopList).toHaveLength(1)

    // search, then the "stopped" view
    await page.getByRole('searchbox').fill('cola')
    await expect(page.getByRole('switch', { name: 'Sotuvda: Cola' })).toBeVisible()
    await expect(page.getByRole('switch', { name: 'Sotuvda: Lavash' })).toHaveCount(0)
    await page.getByRole('searchbox').fill('')
    await page.getByRole('button', { name: "To'xtatilgan (1)" }).click()
    await expect(page.getByRole('switch', { name: 'Sotuvda: Cola' })).toHaveCount(0)

    await page.getByRole('switch', { name: 'Sotuvda: Lavash' }).click()
    await expect(page.getByText("Hozir hech narsa to'xtatilmagan")).toBeVisible()
    expect(api.staff.stopList).toHaveLength(0)
  })

  test('an owner picks the branch first; the stop-list is that branch\'s', async ({ page, api }) => {
    api.signInAs('owner')
    await page.goto('/admin/stoplist')

    await page.getByRole('switch', { name: 'Sotuvda: Cola' }).click()
    await expect.poll(() => api.callsTo('POST', '/owner/stop-list').length).toBe(1)
    expect(api.callsTo('POST', '/owner/stop-list')[0].body).toEqual({ type: 'product', id: 2, branch_id: 1 })

    await page.getByRole('combobox', { name: 'Filial' }).selectOption({ label: 'Yunusobod filiali' })
    await expect(page.getByRole('switch', { name: 'Sotuvda: Cola' })).toHaveAttribute('aria-checked', 'true') // the other branch sells it
  })

  test('an extra (a sauce) can be stopped on its own', async ({ page, api }) => {
    api.signInAs('manager')
    await page.goto('/admin/stoplist')

    await page.getByRole('switch', { name: 'Sotuvda: Zaytun' }).click()

    await expect.poll(() => api.callsTo('POST', '/owner/stop-list').length).toBe(1)
    expect(api.callsTo('POST', '/owner/stop-list')[0].body).toEqual({ type: 'modifier', id: 3 })
  })
})

test.describe('batches', () => {
  test('opens a batch, tops it up, sets what is left and closes it', async ({ page, api }) => {
    api.signInAs('manager')
    await page.goto('/admin/stoplist')
    await page.getByRole('button', { name: 'Qozon', exact: true }).click()
    await expect(page.getByText("Ochiq qozon yo'q")).toBeVisible()

    await page.getByRole('button', { name: 'Yangi qozon ochish' }).click()
    await page.getByLabel('Taom', { exact: true }).selectOption({ label: 'Osh' })
    await page.getByLabel(/Necha porsiya/).fill('40')
    await page.getByLabel(/Izoh/).fill('Tushlik')
    await page.getByRole('button', { name: 'Qozonni ochish' }).click()

    const osh = page.getByRole('article', { name: 'Osh (Standart)' }).or(page.getByRole('article', { name: 'Osh' }))
    await expect(osh.getByText('40 / 40 dona')).toBeVisible()
    expect(api.callsTo('POST', '/owner/batches')[0].body).toEqual({ product_id: 5, quantity: 40, label: 'Tushlik' })

    // 40 + 10, then "what is left" = 12
    await osh.getByRole('button', { name: /^To'ldirish/ }).click()
    let dialog = page.getByRole('dialog')
    await dialog.getByLabel("Qancha qo'shiladi").fill('10')
    await dialog.getByRole('button', { name: 'Saqlash' }).click()
    await expect(osh.getByText('50 / 50 dona')).toBeVisible()

    await osh.getByRole('button', { name: /^To'ldirish/ }).click()
    dialog = page.getByRole('dialog')
    await dialog.getByRole('button', { name: 'Qoldiq', exact: true }).click()
    await dialog.getByLabel('Hozirgi qoldiq').fill('12')
    await dialog.getByRole('button', { name: 'Saqlash' }).click()
    await expect(osh.getByText('12 / 50 dona')).toBeVisible()

    await osh.getByRole('button', { name: /^Yopish/ }).click()
    await page.getByRole('alertdialog').getByRole('button', { name: 'Yopish', exact: true }).click()
    await expect(page.getByText("Ochiq qozon yo'q")).toBeVisible()
    expect(api.staff.batches[0].is_open).toBe(false)
  })

  test('waits for an amount, and shows the refusal of the backend under the field', async ({ page, api }) => {
    api.signInAs('manager')
    await page.goto('/admin/stoplist')
    await page.getByRole('button', { name: 'Qozon', exact: true }).click()
    await page.getByRole('button', { name: 'Yangi qozon ochish' }).click()
    await page.getByLabel('Taom', { exact: true }).selectOption({ label: 'Osh' })
    await page.getByLabel(/Necha porsiya/).fill('0')
    await expect(page.getByRole('button', { name: 'Qozonni ochish' })).toBeDisabled() // zero is not an amount

    await page.getByLabel(/Necha porsiya/).fill('20')
    await page.getByRole('button', { name: 'Qozonni ochish' }).click()
    await expect(page.getByText('20 / 20 dona')).toBeVisible()

    // a second batch of the same dish in the branch
    await page.getByRole('button', { name: 'Yangi qozon ochish' }).click()
    await page.getByLabel('Taom', { exact: true }).selectOption({ label: 'Osh' })
    await page.getByLabel(/Necha porsiya/).fill('10')
    await page.getByRole('button', { name: 'Qozonni ochish' }).click()
    await expect(page.getByText("'Osh' uchun bu filialda qozon allaqachon ochiq.")).toBeVisible()
  })
})
