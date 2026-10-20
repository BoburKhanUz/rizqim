import type { Locator, Page } from '@playwright/test'
import { addLavashToCart } from './cartFlow'
import { expect, test } from './fixtures'
import type { ApiCall } from './fakeApi'

// Picking a delivery address in the cart: by searching for it, or by dropping a pin on the map (the address is then
// read off the pin by the reverse geocoder). The geocoder behind GET /geocode/* is the fake of e2e/fakeApi.ts; the map
// tiles are not reachable (e2e/fixtures.ts), the map itself, its pin and its clicks are real Leaflet.

const CHILONZOR = { address: "Amir Temur ko'chasi, Chilonzor, Toshkent", lat: 41.2791, lng: 69.2104 }
const YUNUSOBOD = { address: "Amir Temur shoh ko'chasi, Yunusobod, Toshkent", lat: 41.3649, lng: 69.2871 }
// Samarkand: far outside the 15 km service radius of the store's only branch (Chilonzor)
const SAMARKAND = { address: "Amir Temur ko'chasi, Samarqand", lat: 39.6542, lng: 66.9597 }

const form = (page: Page): Locator => page.getByText('Yangi manzil', { exact: true }).locator('xpath=..')
const addressInput = (page: Page): Locator => page.getByLabel('Manzil', { exact: true })
const map = (page: Page): Locator => page.locator('.rz-map')
const pin = (page: Page): Locator => map(page).locator('.leaflet-marker-icon')

// The map opens on the branch, zoom 14: one pixel is about 6.4e-5 degrees of latitude and 8.6e-5 of longitude
const DEGREES_PER_PIXEL = { lat: 6.44e-5, lng: 8.58e-5 }
const BRANCH_POINT = { lat: 41.2753, lng: 69.2037 }

async function openNewAddressForm(page: Page) {
  await addLavashToCart(page)
  await page.getByRole('button', { name: "Yangi manzil qo'shish" }).click()
  await expect(addressInput(page)).toBeVisible()
  // Leaflet is loaded on demand; the pin goes on its map
  await expect(map(page).locator('.leaflet-container')).toBeVisible()
}

// A tap on the map, `dx`/`dy` pixels from its centre (the centre is where the branch is)
async function tapMap(page: Page, dx: number, dy: number) {
  const box = await map(page).boundingBox()
  if (!box) throw new Error('the map has no box')
  await map(page).click({ position: { x: box.width / 2 + dx, y: box.height / 2 + dy } })
}

function expectedPoint(dx: number, dy: number) {
  return { lat: BRANCH_POINT.lat - dy * DEGREES_PER_PIXEL.lat, lng: BRANCH_POINT.lng + dx * DEGREES_PER_PIXEL.lng }
}

function pointOf(call: ApiCall) {
  return { lat: Number(call.query.lat), lng: Number(call.query.lng) }
}

async function saveAddress(page: Page) {
  await page.getByRole('button', { name: 'Saqlash', exact: true }).click()
  await expect(page.getByRole('radiogroup', { name: 'Manzillar' })).toBeVisible()
}

test.describe('address search', () => {
  test('customer looks an address up, picks a result and orders to it', async ({ page, api }) => {
    api.places = [CHILONZOR, YUNUSOBOD]
    await openNewAddressForm(page)

    // Nothing is picked yet: no pin, and the address cannot be saved
    await expect(page.getByText('Joylashuv hali belgilanmagan')).toBeVisible()
    await expect(pin(page)).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Saqlash', exact: true })).toBeDisabled()

    await addressInput(page).fill('Amir Temur')
    await page.getByRole('button', { name: 'Manzilni qidirish' }).click()

    const found = page.getByRole('list', { name: 'Topilgan manzillar' })
    await expect(found.getByRole('button')).toHaveText([CHILONZOR.address, YUNUSOBOD.address])
    expect(api.callsTo('GET', '/geocode/search').map((call) => call.query.q)).toEqual(['Amir Temur'])

    await found.getByRole('button', { name: CHILONZOR.address }).click()

    // The result became the address and the pin; the list is gone
    await expect(found).toHaveCount(0)
    await expect(addressInput(page)).toHaveValue(CHILONZOR.address)
    await expect(page.getByText('Joylashuv belgilandi')).toBeVisible()
    await expect(pin(page)).toHaveCount(1)
    // The address of the pin is the result's own: nothing is looked up again
    expect(api.callsTo('GET', '/geocode/reverse')).toHaveLength(0)

    await saveAddress(page)
    await expect(page.getByRole('radio', { name: new RegExp(CHILONZOR.address) })).toBeChecked()

    await page.getByRole('button', { name: /^Buyurtma berish/ }).click()
    await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()

    const [placed] = api.callsTo('POST', '/orders')
    expect(placed.body).toMatchObject({ type: 'delivery', address: CHILONZOR.address, latitude: CHILONZOR.lat, longitude: CHILONZOR.lng })
  })

  test('Enter searches too, and searching again replaces the list', async ({ page, api }) => {
    api.places = [CHILONZOR, YUNUSOBOD]
    await openNewAddressForm(page)

    await addressInput(page).fill('Amir Temur ko')
    await addressInput(page).press('Enter')
    await expect(page.getByRole('list', { name: 'Topilgan manzillar' }).getByRole('button')).toHaveCount(1)

    await addressInput(page).fill('Yunusobod')
    await addressInput(page).press('Enter')
    await expect(page.getByRole('list', { name: 'Topilgan manzillar' }).getByRole('button')).toHaveText([YUNUSOBOD.address])
  })

  test('a search is only sent for three letters or more, and only when asked for', async ({ page, api }) => {
    api.places = [CHILONZOR]
    await openNewAddressForm(page)

    await addressInput(page).fill('Am')
    await expect(page.getByRole('button', { name: 'Manzilni qidirish' })).toBeDisabled()
    await addressInput(page).fill('Amir')
    await expect(page.getByRole('button', { name: 'Manzilni qidirish' })).toBeEnabled()

    // Typing alone never searches (the geocoder's policy forbids as-you-type traffic)
    await page.waitForTimeout(800)
    expect(api.callsTo('GET', '/geocode/search')).toHaveLength(0)
  })

  test('says so when nothing matches and when the geocoder is down, and the address can still be typed and pinned', async ({ page, api }) => {
    api.places = []
    await openNewAddressForm(page)

    await addressInput(page).fill('Mavjud emas')
    await page.getByRole('button', { name: 'Manzilni qidirish' }).click()
    await expect(page.getByText('Hech narsa topilmadi')).toBeVisible()

    api.geocoderDown = true
    await page.getByRole('button', { name: 'Manzilni qidirish' }).click()
    await expect(page.getByText("Manzilni qidirib bo'lmadi")).toBeVisible()
    await expect(page.getByText('Hech narsa topilmadi')).toHaveCount(0)

    // Without a pin the address cannot be saved however it was typed; with one it can, even though nothing resolves
    await expect(page.getByRole('button', { name: 'Saqlash', exact: true })).toBeDisabled()
    await tapMap(page, 40, -30)
    await expect(page.getByText("Manzil matnini aniqlab bo'lmadi")).toBeVisible()
    await expect(page.getByRole('button', { name: 'Saqlash', exact: true })).toBeEnabled()
    await expect(addressInput(page)).toHaveValue('Mavjud emas') // the customer's own text is left alone
  })
})

test.describe('the map pin', () => {
  test('customer taps the map and the address comes from the reverse geocoder', async ({ page, api }) => {
    await openNewAddressForm(page)
    await expect(addressInput(page)).toHaveValue('')

    await tapMap(page, 60, -40)

    await expect(pin(page)).toHaveCount(1)
    await expect(page.getByText('Joylashuv belgilandi')).toBeVisible()
    // The label is what the geocoder answered for the tapped point (after a short pause for corrections)
    await expect(addressInput(page)).toHaveValue(api.reverseAddress ?? '')
    const lookups = api.callsTo('GET', '/geocode/reverse')
    expect(lookups).toHaveLength(1)
    const point = pointOf(lookups[0])
    expect(point.lat).toBeCloseTo(expectedPoint(60, -40).lat, 3)
    expect(point.lng).toBeCloseTo(expectedPoint(60, -40).lng, 3)

    await saveAddress(page)
    await page.getByRole('button', { name: /^Buyurtma berish/ }).click()
    await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()

    // The order carries the pin, not a point of the app's own
    const [placed] = api.callsTo('POST', '/orders')
    expect(placed.body).toMatchObject({ type: 'delivery', address: api.reverseAddress, latitude: point.lat, longitude: point.lng })
  })

  test('several taps in a row cost one lookup, for the last point', async ({ page, api }) => {
    await openNewAddressForm(page)

    await tapMap(page, -50, 20)
    await tapMap(page, 20, 50)
    await tapMap(page, 70, -60)

    await expect(addressInput(page)).toHaveValue(api.reverseAddress ?? '')
    const lookups = api.callsTo('GET', '/geocode/reverse')
    expect(lookups).toHaveLength(1)
    expect(pointOf(lookups[0]).lng).toBeCloseTo(expectedPoint(70, -60).lng, 3)
  })

  test('dragging the pin looks the address of the new spot up', async ({ page, api }) => {
    await openNewAddressForm(page)
    await tapMap(page, 0, 0)
    await expect(addressInput(page)).toHaveValue(api.reverseAddress ?? '')
    expect(api.callsTo('GET', '/geocode/reverse')).toHaveLength(1)
    api.reverseAddress = "Navoi ko'chasi, 5-uy"

    const marker = await pin(page).boundingBox()
    if (!marker) throw new Error('the pin has no box')
    // The pin's tip is at the bottom centre of its image: grab the middle and move it 50 px right and 30 px down
    const from = { x: marker.x + marker.width / 2, y: marker.y + marker.height / 2 }
    await page.mouse.move(from.x, from.y)
    await page.mouse.down()
    await page.mouse.move(from.x + 25, from.y + 15, { steps: 5 })
    await page.mouse.move(from.x + 50, from.y + 30, { steps: 5 })
    await page.mouse.up()

    await expect.poll(() => api.callsTo('GET', '/geocode/reverse').length).toBe(2)
    const [first, second] = api.callsTo('GET', '/geocode/reverse').map(pointOf)
    expect(second.lng).toBeGreaterThan(first.lng)
    expect(second.lat).toBeLessThan(first.lat)
    // The text in the field came from the first pin, not from the customer, so the new pin's address replaces it
    await expect(addressInput(page)).toHaveValue("Navoi ko'chasi, 5-uy")
    await expect(page.getByRole('button', { name: /Xarita bo'yicha manzil/ })).toHaveCount(0)
  })

  test('keeps the address the customer typed and offers the one of the pin instead', async ({ page, api }) => {
    await openNewAddressForm(page)
    await addressInput(page).fill('Uyimning oldi')

    await tapMap(page, 30, 30)

    const offer = page.getByRole('button', { name: /Xarita bo'yicha manzil/ })
    await expect(offer).toContainText(api.reverseAddress ?? '')
    await expect(addressInput(page)).toHaveValue('Uyimning oldi')

    await offer.click()
    await expect(addressInput(page)).toHaveValue(api.reverseAddress ?? '')
    await expect(offer).toHaveCount(0)
  })

  test('says so when the pin has no address, and the customer writes it', async ({ page, api }) => {
    api.reverseAddress = null
    await openNewAddressForm(page)

    await tapMap(page, 10, 10)

    await expect(page.getByText('Bu nuqta uchun manzil topilmadi')).toBeVisible()
    await expect(page.getByText('Joylashuv belgilandi')).toBeVisible() // the pin stays
    await expect(addressInput(page)).toHaveValue('')
    await expect(page.getByRole('button', { name: 'Saqlash', exact: true })).toBeDisabled() // no text yet

    await addressInput(page).fill("Bog'ishamol, 7")
    await expect(page.getByRole('button', { name: 'Saqlash', exact: true })).toBeEnabled()
    await saveAddress(page)
    await page.getByRole('button', { name: /^Buyurtma berish/ }).click()
    await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()
    expect(api.callsTo('POST', '/orders')[0].body).toMatchObject({ address: "Bog'ishamol, 7" })
  })

  test('cancelling the form drops the pin and nothing is saved', async ({ page, api }) => {
    await openNewAddressForm(page)
    await tapMap(page, 10, 10)
    await expect(addressInput(page)).toHaveValue(api.reverseAddress ?? '')

    await form(page).getByRole('button', { name: 'Bekor qilish' }).click()

    await expect(page.getByRole('radiogroup', { name: 'Manzillar' })).toHaveCount(0)
    await page.getByRole('button', { name: "Yangi manzil qo'shish" }).click()
    await expect(addressInput(page)).toHaveValue('')
    await expect(pin(page)).toHaveCount(0)
  })
})

test.describe('the service area', () => {
  test('an address outside the delivery zone is refused by the quote before ordering, and a closer one then goes through', async ({ page, api }) => {
    api.places = [SAMARKAND, CHILONZOR]
    await openNewAddressForm(page)

    await addressInput(page).fill('Amir Temur')
    await page.getByRole('button', { name: 'Manzilni qidirish' }).click()
    await page.getByRole('list', { name: 'Topilgan manzillar' }).getByRole('button', { name: SAMARKAND.address }).click()
    await saveAddress(page)

    // The quote says it, in Uzbek, and the order cannot be placed
    const refusal = page.getByRole('alert').filter({ hasText: 'Bu manzilga yetkazib berilmaydi' })
    await expect(refusal).toContainText('Boshqa manzilni tanlang')
    await expect(page.getByRole('button', { name: /^Bu manzilga yetkazib berilmaydi/ })).toBeDisabled()
    expect(api.callsTo('GET', '/delivery/quote')[0].query).toMatchObject({ store: '1', lat: String(SAMARKAND.lat), lng: String(SAMARKAND.lng), subtotal: '25000' })
    expect(api.callsTo('POST', '/orders')).toHaveLength(0)

    // Another address, inside the zone
    await page.getByRole('button', { name: "Yangi manzil qo'shish" }).click()
    await addressInput(page).fill('Amir Temur')
    await page.getByRole('button', { name: 'Manzilni qidirish' }).click()
    await page.getByRole('list', { name: 'Topilgan manzillar' }).getByRole('button', { name: CHILONZOR.address }).click()
    await saveAddress(page)
    await expect(page.getByRole('radio', { name: new RegExp(CHILONZOR.address) })).toBeChecked()
    await expect(page.getByText(/Yetkazib berish: 12\s000/)).toBeVisible()

    await page.getByRole('button', { name: /^Buyurtma berish/ }).click()
    await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()
    expect(api.orders).toHaveLength(1)
    expect(api.callsTo('POST', '/orders')[0].body).toMatchObject({ address: CHILONZOR.address, latitude: CHILONZOR.lat, longitude: CHILONZOR.lng })
  })

  test('a store without zones keeps the radius rule: the server refuses at checkout and the customer is told in plain Uzbek', async ({ page, api }) => {
    api.zonesConfigured = false
    api.places = [SAMARKAND]
    await openNewAddressForm(page)

    await addressInput(page).fill('Amir Temur')
    await page.getByRole('button', { name: 'Manzilni qidirish' }).click()
    await page.getByRole('list', { name: 'Topilgan manzillar' }).getByRole('button', { name: SAMARKAND.address }).click()
    await saveAddress(page)

    await page.getByRole('button', { name: /^Buyurtma berish/ }).click()

    const alert = page.getByRole('alertdialog')
    await expect(alert).toContainText('Bu manzilga yetkazib berilmaydi')
    await alert.getByRole('button', { name: 'OK' }).click()
    await expect(alert).toBeHidden()
    expect(api.orders).toHaveLength(0)
    expect(api.callsTo('POST', '/orders')[0].status).toBe(400)
    // The cart is still there
    await expect(page.getByText('Lavash (Standart)')).toBeVisible()
  })
})
