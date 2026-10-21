import { expect, openSignedIn, test } from './fixtures'

// The stop-list: one tap stops a dish in a branch, one tap puts it back; a reason can go with it; each branch has its own.

test('a dish is stopped and put back on sale', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/stoplist')
  await expect(page.getByText('Stop-list bo\'sh')).toBeVisible()

  await page.getByRole('button', { name: 'Tugadi Osh' }).click()

  await expect(page.getByText('Hozir stop-listda: 1')).toBeVisible()
  const stopped = page.getByRole('listitem').filter({ hasText: 'Stop-listda' })
  await expect(stopped.filter({ hasText: 'Osh' }).first()).toBeVisible()
  const osh = api.menu.products.find((product) => product.name === 'Osh')!
  expect(api.callsTo('POST', '/v1/owner/stop-list')[0].body).toEqual({ branch_id: 1, type: 'product', id: osh.id })

  await page.getByRole('button', { name: 'Sotuvga qaytarish Osh' }).first().click()
  await expect(page.getByText('Hozir stop-listda: 0')).toBeVisible()
  await expect(page.getByText("Stop-list bo'sh")).toBeVisible()
  expect(api.callsTo('DELETE', /^\/v1\/owner\/stop-list\/\d+$/)).toHaveLength(1)
  expect(api.menu.stopList).toHaveLength(0)
})

test('a reason goes with the stop', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/stoplist')

  await page.getByRole('button', { name: "Sabab va vaqt bilan to'xtatish: Osh" }).click()
  const dialog = page.getByRole('dialog', { name: "Sabab va vaqt bilan to'xtatish" })
  await dialog.getByLabel('Sabab (ixtiyoriy)').fill('Guruch tugadi')
  await dialog.getByRole('button', { name: 'Tugadi' }).click()

  await expect(dialog).toHaveCount(0)
  await expect(page.getByText('Guruch tugadi')).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/stop-list')[0].body).toMatchObject({ type: 'product', reason: 'Guruch tugadi' })
})

test('each branch has its own stop-list', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/stoplist')
  await page.getByRole('button', { name: 'Tugadi Lagmon' }).click()
  await expect(page.getByText('Hozir stop-listda: 1')).toBeVisible()

  await page.getByLabel('Filial', { exact: true }).last().selectOption({ label: 'Yunusobod' })
  await expect(page.getByText('Hozir stop-listda: 0')).toBeVisible()
  expect(api.callsTo('GET', '/v1/owner/stop-list').at(-1)?.query).toMatchObject({ branch_id: '2' })

  await page.getByLabel('Filial', { exact: true }).last().selectOption({ label: 'Chilonzor' })
  await expect(page.getByText('Hozir stop-listda: 1')).toBeVisible()
})
