import { expect, test } from './fixtures'

test('courier picks up an assigned order and hands it over', async ({ page, api }) => {
  const courier = api.signInAs('courier')
  const order = api.addOrder({ status: 'courier_assigned', order_type: 'delivery', type: 'delivery', courier_id: courier.id, short_code: 'D300', address: 'Chilonzor 9, 14-uy' })

  await page.goto('/courier')
  await expect(page.getByRole('heading', { name: courier.name })).toBeVisible()
  await expect(page.getByRole('button', { name: /^Faol \(1\)$/ })).toBeVisible()
  await expect(page.getByText('#D300')).toBeVisible()
  await expect(page.getByText('Mijoz Test')).toBeVisible()
  await expect(page.getByText('Lavash x1')).toBeVisible()
  await expect(page.getByText('Chilonzor 9, 14-uy')).toBeVisible()

  // courier_assigned -> on_the_way
  await page.getByRole('button', { name: /Olib ketdim/ }).click()
  expect(api.callsTo('POST', `/orders/${order.uuid}/status`)[0].body).toEqual({ status: 'on_the_way' })
  await expect(page.getByRole('button', { name: /Yetkazib berdim/ })).toBeVisible()
  await expect(page.getByRole('button', { name: /Olib ketdim/ })).toHaveCount(0)

  // on_the_way -> delivered: the delivery leaves the active list and is counted among today's
  await page.getByRole('button', { name: /Yetkazib berdim/ }).click()
  expect(api.callsTo('POST', `/orders/${order.uuid}/complete`)).toHaveLength(1)
  await expect(page.getByText('Sizga hali buyurtma biriktirilmagan.')).toBeVisible()
  await expect(page.getByRole('button', { name: /^Faol \(0\)$/ })).toBeVisible()
  await expect(page.getByRole('button', { name: /^Topshirilganlar \(1\)$/ })).toBeVisible()
})
