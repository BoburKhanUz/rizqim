import { expect, openSignedIn, test } from './fixtures'

// Ctrl+K: the command palette over GET /admin/search.

test('Ctrl+K opens the search; results come grouped by type, masked, and Enter opens the chosen one', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/today')
  await expect(page.getByRole('heading', { name: 'Bugun', level: 1 })).toBeVisible()

  await page.keyboard.press('Control+k')
  const palette = page.getByRole('dialog', { name: 'Qidirish' })
  await expect(palette).toBeVisible()
  const box = palette.getByRole('combobox')
  await expect(box).toBeFocused()
  await expect(palette.getByText('Kamida 2 ta belgi yozing')).toBeVisible()

  await box.pressSequentially('lazzat')
  await expect(palette.getByRole('option', { name: /^Lazzat Oshxona/ })).toBeVisible()
  await expect(palette.getByRole('group', { name: 'Ovqatxonalar' }).getByRole('option')).toHaveCount(2)
  // Phones are masked by the backend and shown as they come
  await expect(palette.getByRole('option', { name: /^Lazzat Oshxona/ })).toContainText('+998 90 *** ** 67')
  // One request for the word, not one per key
  expect(api.callsTo('GET', '/search').filter((call) => call.query.q === 'lazzat')).toHaveLength(1)
  expect(api.callsTo('GET', '/search').filter((call) => call.query.q.length === 1)).toHaveLength(0)

  await box.press('ArrowDown')
  await expect(palette.getByRole('option', { name: /^Lazzat Express/ })).toHaveAttribute('aria-selected', 'true')
  await box.press('Enter')
  await expect(page).toHaveURL(/\/stores\/13$/)
  await expect(page.getByText("Bu bo'lim keyingi to'lqinda qo'shiladi")).toBeVisible()
  await expect(palette).toHaveCount(0)
})

test('the search groups stores, orders, users and team members, and Escape closes it', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/audit')
  await page.getByRole('button', { name: /Qidirish/ }).first().click()
  const palette = page.getByRole('dialog', { name: 'Qidirish' })
  const box = palette.getByRole('combobox')

  await box.fill('ab')
  await expect(palette.getByRole('group', { name: 'Buyurtmalar' }).getByRole('option', { name: /AB12CD/ })).toBeVisible()
  await box.fill('olim')
  await expect(palette.getByRole('group', { name: 'Foydalanuvchilar' }).getByRole('option', { name: /Olim Egamov/ })).toBeVisible()
  await box.fill('sardor')
  await expect(palette.getByRole('group', { name: 'Jamoa' }).getByRole('option', { name: /Sardor Operator/ })).toBeVisible()
  await box.fill('zzzz')
  await expect(palette.getByText('Hech narsa topilmadi')).toBeVisible()

  await page.keyboard.press('Escape')
  await expect(palette).toHaveCount(0)
  await expect(page.getByRole('heading', { name: 'Harakatlar jurnali', level: 1 })).toBeVisible()
})

test('a member sees only the kinds of results the role allows', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/today', 'sardor@rizqim.uz')
  await expect(page.getByRole('heading', { name: 'Bugun', level: 1 })).toBeVisible()
  await page.keyboard.press('Control+k')
  const box = page.getByRole('dialog', { name: 'Qidirish' }).getByRole('combobox')
  // An operator has stores and orders, no users and no team
  await box.fill('olim')
  await expect(page.getByText('Hech narsa topilmadi')).toBeVisible()
  await box.fill('lazzat')
  await expect(page.getByRole('option', { name: /^Lazzat Oshxona/ })).toBeVisible()
  await box.fill('sardor')
  await expect(page.getByText('Hech narsa topilmadi')).toBeVisible()
})

test('a search result for a team member opens the team list with that member shown', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/today')
  await expect(page.getByRole('heading', { name: 'Bugun', level: 1 })).toBeVisible()
  await page.keyboard.press('Control+k')
  await page.getByRole('dialog', { name: 'Qidirish' }).getByRole('combobox').fill('sardor')
  await page.getByRole('option', { name: /Sardor Operator/ }).click()
  await expect(page).toHaveURL(/\/team\/2$/)
  await expect(page.getByRole('heading', { name: /^Sardor Operator/ })).toBeVisible()
})
