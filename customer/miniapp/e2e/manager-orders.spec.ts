import { makeOrderItem } from '../src/test/factories'
import { expect, test } from './fixtures'

// P2-25: the manager's order cards and the order screen show what phase 1 added: modifiers, weights, notes, the kitchen's
// progress and the waiting time (GET /staff/orders, GET /staff/orders/{uuid}).

test('a card shows the modifiers, the weight, the notes, the ready lines and the waiting time', async ({ page, api }) => {
  api.signInAs('manager')
  const order = api.addOrder({
    status: 'preparing',
    short_code: 'K100',
    order_type: 'pickup',
    type: 'pickup',
    notes: 'Piyozsiz, iltimos',
    items: [
      makeOrderItem({ id: 1, product_name: 'Burger', variant_name: 'Standart', quantity: 2, modifiers: [{ id: 2, uuid: 'm2', group_id: 1, group_name: 'Sous', name: 'Pishloqli', price_delta: '3000.00', quantity: 1, total: '6000.00' }] }),
      makeOrderItem({ id: 2, product_name: 'Qazi', variant_name: 'kg', quantity: 1.5, unit: 'kg' }),
    ],
  })
  api.staff.extras[order.uuid] = { readyItemIds: [1], acceptedMinutesAgo: 22 }

  await page.goto('/manager')

  const card = page.getByRole('article', { name: 'Buyurtma #K100' })
  await expect(card.getByText('Burger x2')).toBeVisible()
  await expect(card.getByText('Pishloqli')).toBeVisible()
  await expect(card.getByText(/^Qazi 1,5\skg$/)).toBeVisible()
  await expect(card.getByText('Piyozsiz, iltimos')).toBeVisible()
  await expect(card.getByText('Oshxona: 1/2 tayyor')).toBeVisible()
  await expect(card.getByText('Tayyor', { exact: true })).toHaveCount(1)
  await expect(card.getByText(/^Kutmoqda: 2[12] daq$/)).toBeVisible()
})

test('the order screen has the lines, the sums and the history, and the main button takes the next step', async ({ page, api }) => {
  api.signInAs('manager')
  const order = api.addOrder({
    status: 'accepted',
    short_code: 'D200',
    order_type: 'pickup',
    type: 'pickup',
    items: [makeOrderItem({ id: 1, product_name: 'Lavash', quantity: 3, total_price: '75000.00' })],
    subtotal: '75000.00',
    total_price: '75000.00',
  })
  api.staff.extras[order.uuid] = { acceptedMinutesAgo: 3 }

  await page.goto('/manager')
  await page.getByRole('button', { name: '#D200 buyurtmasi: batafsil' }).click()

  await expect(page.getByRole('heading', { name: 'Buyurtma #D200', level: 1 })).toBeVisible()
  await expect(page.getByText('Lavash x3')).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Holatlar tarixi' })).toBeVisible()
  await expect(page.getByRole('link', { name: /Qo'ng'iroq qilish: Mijoz Test/ })).toHaveAttribute('href', 'tel:+998909998877')

  // accepted -> preparing, on the main button
  await page.getByRole('button', { name: 'Tayyorlashni boshlash' }).click()
  await expect.poll(() => api.callsTo('POST', `/staff/orders/${order.uuid}/status`).length).toBe(1)
  expect(api.callsTo('POST', `/staff/orders/${order.uuid}/status`)[0].body).toEqual({ status: 'preparing' })
  await expect(page.getByRole('button', { name: "Tayyor bo'ldi" })).toBeVisible()
})

test('an owner with two branches narrows the board to one', async ({ page, api }) => {
  api.signInAs('owner')
  api.addOrder({ status: 'new', short_code: 'C1', branch_id: 1, order_type: 'pickup', type: 'pickup' })
  api.addOrder({ status: 'new', short_code: 'Y1', branch_id: 2, order_type: 'pickup', type: 'pickup' })

  await page.goto('/manager')
  await expect(page.getByText('#C1')).toBeVisible()
  await expect(page.getByText('#Y1')).toBeVisible()

  await page.getByRole('combobox', { name: "Filial bo'yicha" }).selectOption({ label: 'Yunusobod filiali' })

  await expect(page.getByText('#C1')).toHaveCount(0)
  await expect(page.getByText('#Y1')).toBeVisible()
  const lastList = api.callsTo('GET', '/staff/orders').filter((call) => call.query.scope === 'active').at(-1)
  expect(lastList?.query.branch_id).toBe('2')
})

test('a manager has no branch filter and sees only their branch', async ({ page, api }) => {
  api.signInAs('manager')
  api.addOrder({ status: 'new', short_code: 'C1', branch_id: 1, order_type: 'pickup', type: 'pickup' })
  api.addOrder({ status: 'new', short_code: 'Y1', branch_id: 2, order_type: 'pickup', type: 'pickup' })

  await page.goto('/manager')

  await expect(page.getByText('#C1')).toBeVisible()
  await expect(page.getByText('#Y1')).toHaveCount(0)
  await expect(page.getByRole('combobox', { name: "Filial bo'yicha" })).toHaveCount(0)
})
