import { expect, openSignedIn, test } from './fixtures'

// Staff: the owner adds a member (who gets an invite link and can sign in by SMS with the phone), gives them a PIN for the
// kitchen terminal, and the server's refusals show in its words.

test('the owner adds a member and sets a PIN', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/staff')
  await expect(page.getByRole('rowheader', { name: /Malika Menejer/ })).toBeVisible()

  // --- A new member: a cook of the Chilonzor branch, who signs in by SMS with this phone
  await page.getByRole('button', { name: "Xodim qo'shish" }).first().click()
  const form = page.getByRole('dialog', { name: 'Yangi xodim' })
  await form.getByLabel('Ismi').fill('Dilshod Oshpaz')
  await form.getByLabel('Roli').selectOption('cook')
  await form.getByLabel('Filial').selectOption({ label: 'Chilonzor' })
  await form.getByLabel('Kirish telefoni').fill('93 555 11 22')
  await form.getByRole('button', { name: "Xodim qo'shish" }).click()

  // The invite link is shown once
  const invite = page.getByRole('dialog', { name: 'Dilshod Oshpaz uchun taklif havolasi' })
  await expect(invite.getByLabel('Taklif havolasi')).toHaveValue(/^https:\/\/t\.me\/e2e_bot\?start=invite_\d+$/)
  await expect(invite.getByText('SMS kod bilan ham kira oladi', { exact: false })).toBeVisible()
  await invite.getByRole('button', { name: 'Tayyor' }).click()

  await expect(page.getByText("Dilshod Oshpaz qo'shildi.")).toBeVisible()
  const row = page.getByRole('row').filter({ hasText: 'Dilshod Oshpaz' })
  await expect(row).toContainText('Oshpaz')
  await expect(row).toContainText("PIN yo'q")
  const added = api.team.members.find((member) => member.name === 'Dilshod Oshpaz')!
  expect(api.callsTo('POST', '/v1/owner/staff')[0].body).toEqual({ name: 'Dilshod Oshpaz', role: 'cook', branch_id: 1, phone: '+998935551122' })

  // --- A PIN: an easy one is refused in Uzbek, a good one is saved and the staff code shown
  await page.getByRole('button', { name: 'Dilshod Oshpaz: PIN kod' }).click()
  const pin = page.getByRole('dialog', { name: 'Dilshod Oshpaz: PIN kod' })
  await pin.getByLabel('PIN kod').fill('1234')
  await pin.getByRole('button', { name: "PIN o'rnatish" }).click()
  await expect(pin.getByText('PIN juda oddiy: boshqasini tanlang.').first()).toBeVisible()

  await pin.getByLabel('PIN kod').fill('4831')
  await pin.getByRole('button', { name: "PIN o'rnatish" }).click()
  const saved = page.getByRole('dialog', { name: 'PIN saqlandi' })
  await expect(saved).toContainText(`K-${added.id}`)
  await saved.getByRole('button', { name: 'Tayyor' }).click()

  await expect(page.getByText('Dilshod Oshpaz uchun PIN saqlandi.')).toBeVisible()
  await expect(row).toContainText('PIN bor')
  expect(api.callsTo('PUT', `/v1/owner/staff/${added.id}/pin`).map((call) => call.body)).toEqual([{ pin: '1234' }, { pin: '4831' }])
  expect(api.team.pins.get(added.id)).toBe('4831')
})

test('a phone another member already signs in with is refused', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/staff')
  await page.getByRole('button', { name: "Xodim qo'shish" }).first().click()
  const form = page.getByRole('dialog', { name: 'Yangi xodim' })
  await form.getByLabel('Ismi').fill('Yana Biri')
  await form.getByLabel('Filial').selectOption({ label: 'Chilonzor' })
  // Malika Menejer's number
  await form.getByLabel('Kirish telefoni').fill('90 111 22 33')
  await form.getByRole('button', { name: "Xodim qo'shish" }).click()

  await expect(form.getByText("Bu raqam do'koningizning boshqa xodimiga biriktirilgan.")).toBeVisible()
})
