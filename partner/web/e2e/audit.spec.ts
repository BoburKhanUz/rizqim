import { expect, openSignedIn, test } from './fixtures'

// P4-32: the store's harakatlar jurnali (the page the old store panel had): the owner reads who did what and narrows it
// to one kind of action.

test('the owner reads the store log and filters it by the kind of action', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/audit')
  await expect(page.getByRole('heading', { name: 'Harakatlar jurnali', level: 1 })).toBeVisible()
  const table = page.getByRole('table', { name: 'Harakatlar jurnali' })
  await expect(table).toContainText('Buyurtma bekor qilindi')
  await expect(table).toContainText('status: new → canceled; reason: out_of_stock')
  await expect(table).toContainText("PIN o'rnatildi")

  await page.getByLabel('Harakat turi').selectOption('order')
  await expect(table).not.toContainText("PIN o'rnatildi")
  expect(api.callsTo('GET', '/v1/owner/audit-logs').at(-1)?.query.action).toBe('order.*')
})
