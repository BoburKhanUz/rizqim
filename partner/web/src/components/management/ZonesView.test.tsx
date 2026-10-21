import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { makeBranch, makeZone, ok, permissions, refusal, signIn } from './testSupport';
import type { ZoneMapProps } from './ZoneMapCanvas';
import { ZonesView } from './ZonesView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

// The map itself is Leaflet's (ZoneMapCanvas.test.tsx covers what it asks of the map): here it is a few buttons that do what a
// click, a drag and a click on a zone would do, and a line saying what it was given.
vi.mock('./ZoneMap', () => ({
  ZoneMap: (props: ZoneMapProps) => (
    <div data-testid="map" data-editing={props.editor ? 'yes' : 'no'} data-selected={String(props.selectedId)} data-branches={props.branches.length} data-zones={props.zones.length}>
      <button type="button" onClick={() => props.dispatch({ type: 'addPoint', point: [41.3 + props.editor!.shapes.flatMap((s) => s.outer).length / 100, 69.2] })}>
        map: click
      </button>
      <button type="button" onClick={() => props.onSelectZone(props.zones[0]!.id)}>
        map: click first zone
      </button>
    </div>
  ),
}));

beforeEach(signIn);
afterEach(() => setSession(null));

const base = (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[]) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => permissions(keys),
    'GET /api/v1/owner/branches': () => ok([makeBranch(), makeBranch({ id: 2, name: 'Yunusobod', latitude: null, longitude: null })]),
    'GET /api/v1/owner/delivery-zones': () => ok([makeZone(), makeZone({ id: 8, name: 'Chekka', active: false, branch_id: null, branch: null as never, free_delivery_from: null, eta_minutes: null, delivery_price: '0.00' })]),
    ...over,
  });

describe('ZonesView: the list and the map', () => {
  it('lists the zones with their conditions and gives the map the zones and the branches that have a location', async () => {
    base();
    renderPage(<ZonesView />);
    const zone = await screen.findByRole('button', { name: 'Markaz hududini ochish' });
    expect(within(zone).getByText("Narx 12 000 so'm · eng kam buyurtma 30 000 so'm · 150 000 so'm dan bepul · 40 daq")).toBeInTheDocument();
    expect(within(zone).getByText('Faol')).toBeInTheDocument();
    expect(within(zone).getByText('Markaz · ustuvorlik 2')).toBeInTheDocument();
    const inactive = screen.getByRole('button', { name: 'Chekka hududini ochish' });
    expect(within(inactive).getByText('Faol emas')).toBeInTheDocument();
    expect(within(inactive).getByText('Eng yaqin ochiq filial · ustuvorlik 2')).toBeInTheDocument();
    const map = screen.getByTestId('map');
    expect(map).toHaveAttribute('data-zones', '2');
    expect(map).toHaveAttribute('data-branches', '1'); // the branch without coordinates is not on the map
    expect(map).toHaveAttribute('data-editing', 'no');
  });

  it('says so when there are no zones', async () => {
    base({ 'GET /api/v1/owner/delivery-zones': () => ok([]) });
    renderPage(<ZonesView />);
    expect(await screen.findByText("Hudud yo'q")).toBeInTheDocument();
  });

  it('is for the owner: another role is told so', async () => {
    base({}, ['orders.view']);
    renderPage(<ZonesView />);
    expect(await screen.findByText("Bu amal uchun ruxsatingiz yo'q.")).toBeInTheDocument();
    expect(screen.getByText("Yetkazish hududlarini faqat do'kon egasi o'zgartira oladi.")).toBeInTheDocument();
  });

  it('opens a zone for editing when it is clicked on the map', async () => {
    base();
    const typing = userEvent.setup();
    renderPage(<ZonesView />);
    await typing.click(await screen.findByRole('button', { name: 'map: click first zone' }));
    expect(await screen.findByLabelText('Nomi')).toHaveValue('Markaz');
    expect(screen.getByTestId('map')).toHaveAttribute('data-selected', '7');
  });
});

describe('ZonesView: draw a new zone', () => {
  it('saves a zone drawn with three clicks, with its price, minimum, free-delivery threshold, priority and branch', async () => {
    const { calls } = base({ 'POST /api/v1/owner/delivery-zones': () => ok(makeZone({ id: 9, name: 'Yangi hudud' }), 201) });
    const typing = userEvent.setup();
    renderPage(<ZonesView />);
    await typing.click(await screen.findByRole('button', { name: 'Yangi hudud' }));
    expect(screen.getByTestId('map')).toHaveAttribute('data-editing', 'yes');
    expect(screen.getByText(/Xaritani bosib nuqta qo'shing/)).toBeInTheDocument();

    for (let i = 0; i < 3; i++) await typing.click(screen.getByRole('button', { name: 'map: click' }));
    expect(screen.getByText('Shakllar: 1 · nuqtalar: 3')).toBeInTheDocument();

    await typing.type(screen.getByLabelText('Nomi'), 'Yangi hudud');
    await typing.selectOptions(screen.getByLabelText('Filial'), 'Markaz');
    await typing.type(screen.getByLabelText('Yetkazish narxi'), '15 000');
    await typing.type(screen.getByLabelText('Eng kam buyurtma'), '40000');
    await typing.type(screen.getByLabelText('Bepul yetkazish chegarasi'), '200000');
    await typing.type(screen.getByLabelText('Taxminiy vaqt'), '45');
    await typing.clear(screen.getByLabelText('Ustuvorlik'));
    await typing.type(screen.getByLabelText('Ustuvorlik'), '3');
    await typing.click(screen.getByRole('button', { name: 'Hududni saqlash' }));

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent("Yangi hudud hududi qo'shildi."));
    const body = await calls.find((call) => call.method === 'POST')!.json();
    expect(body).toEqual({
      name: 'Yangi hudud',
      polygon: { type: 'Polygon', coordinates: [[[69.2, 41.3], [69.2, 41.31], [69.2, 41.32], [69.2, 41.3]]] },
      branch_id: 1,
      priority: 3,
      active: true,
      delivery_price: 15000,
      min_order_amount: 40000,
      free_delivery_from: 200000,
      eta_minutes: 45,
    });
    // Back to the list
    expect(screen.getByTestId('map')).toHaveAttribute('data-editing', 'no');
  });

  it('asks for a name, a number that is a number and a shape before calling the server', async () => {
    const { calls } = base();
    const typing = userEvent.setup();
    renderPage(<ZonesView />);
    await typing.click(await screen.findByRole('button', { name: 'Yangi hudud' }));
    await typing.type(screen.getByLabelText('Yetkazish narxi'), 'qimmat');
    await typing.click(screen.getByRole('button', { name: 'Hududni saqlash' }));

    expect(screen.getByText('Nomini kiriting.')).toBeInTheDocument();
    expect(screen.getByText('Son kiriting (masalan, 12000).')).toBeInTheDocument();
    expect(screen.getByText('Hududni xaritada chizing (kamida 3 ta nuqta).')).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'POST')).toBe(false);
  });

  it("shows the server's reason for a bad polygon, in Uzbek, beside the map", async () => {
    base({ 'POST /api/v1/owner/delivery-zones': () => refusal('Xato', 422, { errors: { polygon: ["Ko'pburchakda kamida 3 ta har xil nuqta bo'lishi kerak."] } }) });
    const typing = userEvent.setup();
    renderPage(<ZonesView />);
    await typing.click(await screen.findByRole('button', { name: 'Yangi hudud' }));
    for (let i = 0; i < 3; i++) await typing.click(screen.getByRole('button', { name: 'map: click' }));
    await typing.type(screen.getByLabelText('Nomi'), 'X');
    await typing.click(screen.getByRole('button', { name: 'Hududni saqlash' }));

    const toolbar = within(await screen.findByLabelText('Hududni chizish'));
    expect(await toolbar.findByRole('alert')).toHaveTextContent("Ko'pburchakda kamida 3 ta har xil nuqta bo'lishi kerak.");
    // The zone is still being edited, nothing is lost
    expect(screen.getByLabelText('Nomi')).toHaveValue('X');
    expect(screen.getByText('Shakllar: 1 · nuqtalar: 3')).toBeInTheDocument();
  });

  it('shows a refusal of a price under its field', async () => {
    base({ 'POST /api/v1/owner/delivery-zones': () => refusal('Xato', 422, { errors: { delivery_price: ["Narx 99 999 999 dan oshmasin."] } }) });
    const typing = userEvent.setup();
    renderPage(<ZonesView />);
    await typing.click(await screen.findByRole('button', { name: 'Yangi hudud' }));
    for (let i = 0; i < 3; i++) await typing.click(screen.getByRole('button', { name: 'map: click' }));
    await typing.type(screen.getByLabelText('Nomi'), 'X');
    await typing.type(screen.getByLabelText('Yetkazish narxi'), '999999999');
    await typing.click(screen.getByRole('button', { name: 'Hududni saqlash' }));
    expect(await screen.findByText('Narx 99 999 999 dan oshmasin.')).toBeInTheDocument();
  });

  it('has toolbar buttons to close a shape, undo a point, remove a shape and clear', async () => {
    base();
    const typing = userEvent.setup();
    renderPage(<ZonesView />);
    await typing.click(await screen.findByRole('button', { name: 'Yangi hudud' }));
    const toolbar = within(screen.getByLabelText('Hududni chizish'));
    expect(toolbar.getByRole('button', { name: 'Shaklni yopish' })).toBeDisabled();

    for (let i = 0; i < 3; i++) await typing.click(screen.getByRole('button', { name: 'map: click' }));
    await typing.click(toolbar.getByRole('button', { name: 'Oxirgi nuqtani olib tashlash' }));
    expect(screen.getByText('Shakllar: 1 · nuqtalar: 2')).toBeInTheDocument();
    await typing.click(screen.getByRole('button', { name: 'map: click' }));
    await typing.click(toolbar.getByRole('button', { name: 'Shaklni yopish' }));
    expect(toolbar.queryByRole('button', { name: 'Shaklni yopish' })).not.toBeInTheDocument();
    expect(screen.getByText(/Nuqtani sudrab joyini o'zgartiring/)).toBeInTheDocument();

    await typing.click(toolbar.getByRole('button', { name: 'Yangi shakl' }));
    await typing.click(screen.getByRole('button', { name: 'map: click' }));
    expect(screen.getByText('Shakllar: 2 · nuqtalar: 4')).toBeInTheDocument();
    await typing.click(toolbar.getByRole('button', { name: "2-shaklni o'chirish" }));
    expect(screen.getByText('Shakllar: 1 · nuqtalar: 3')).toBeInTheDocument();
    await typing.click(toolbar.getByRole('button', { name: 'Hammasini tozalash' }));
    expect(screen.getByText('Shakllar: 0 · nuqtalar: 0')).toBeInTheDocument();
  });
});

describe('ZonesView: the GeoJSON box', () => {
  const polygon = JSON.stringify({ type: 'Polygon', coordinates: [[[69.2, 41.3], [69.3, 41.3], [69.3, 41.4], [69.2, 41.3]]] });

  it('takes a pasted shape and a bad one is told off', async () => {
    base();
    const typing = userEvent.setup();
    renderPage(<ZonesView />);
    await typing.click(await screen.findByRole('button', { name: 'Yangi hudud' }));
    await typing.click(screen.getByText('GeoJSON', { selector: 'summary' }));
    const box = screen.getByRole('textbox', { name: 'GeoJSON' });

    await typing.click(box);
    await typing.paste('{"type":"Point"}');
    await typing.click(screen.getByRole('button', { name: "Xaritaga qo'llash" }));
    expect(await screen.findByText("GeoJSON noto'g'ri. Polygon yoki MultiPolygon bo'lsin.")).toBeInTheDocument();

    await typing.clear(box);
    await typing.click(box);
    await typing.paste(polygon);
    await typing.click(screen.getByRole('button', { name: "Xaritaga qo'llash" }));
    expect(await screen.findByText('Shakllar: 1 · nuqtalar: 3')).toBeInTheDocument();
    expect(screen.queryByText(/GeoJSON noto'g'ri/)).not.toBeInTheDocument();
  });
});

describe('ZonesView: edit and delete', () => {
  it('shows the zone being edited and sends only what changed (not the shape when it did not move)', async () => {
    const { calls } = base({ 'PATCH /api/v1/owner/delivery-zones/7': () => ok(makeZone({ delivery_price: '15000.00' })) });
    const typing = userEvent.setup();
    renderPage(<ZonesView />);
    await typing.click(await screen.findByRole('button', { name: 'Markaz hududini ochish' }));

    expect(screen.getByLabelText('Yetkazish narxi')).toHaveValue('12000');
    expect(screen.getByLabelText('Eng kam buyurtma')).toHaveValue('30000');
    expect(screen.getByLabelText('Bepul yetkazish chegarasi')).toHaveValue('150000');
    expect(screen.getByLabelText('Taxminiy vaqt')).toHaveValue('40');
    expect(screen.getByLabelText('Ustuvorlik')).toHaveValue('2');
    expect(screen.getByLabelText('Filial')).toHaveValue('1');
    expect(screen.getByText('Maydoni: 12.50 km²')).toBeInTheDocument();
    expect(screen.getByText('Shakllar: 1 · nuqtalar: 3')).toBeInTheDocument();

    await typing.clear(screen.getByLabelText('Yetkazish narxi'));
    await typing.type(screen.getByLabelText('Yetkazish narxi'), '15000');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Markaz saqlandi.'));
    expect(await calls.find((call) => call.method === 'PATCH')!.json()).toEqual({ delivery_price: 15000 });
  });

  it('sends the shape when a point was moved, and an empty free-delivery threshold as null', async () => {
    const { calls } = base({ 'PATCH /api/v1/owner/delivery-zones/7': () => ok(makeZone()) });
    const typing = userEvent.setup();
    renderPage(<ZonesView />);
    await typing.click(await screen.findByRole('button', { name: 'Markaz hududini ochish' }));
    await typing.clear(screen.getByLabelText('Bepul yetkazish chegarasi'));
    await typing.click(screen.getByText('GeoJSON', { selector: 'summary' }));
    const box = screen.getByRole('textbox', { name: 'GeoJSON' });
    await typing.clear(box);
    await typing.click(box);
    await typing.paste(JSON.stringify({ type: 'Polygon', coordinates: [[[69.2, 41.3], [69.35, 41.3], [69.3, 41.4], [69.2, 41.3]]] }));
    await typing.click(screen.getByRole('button', { name: "Xaritaga qo'llash" }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(calls.some((call) => call.method === 'PATCH')).toBe(true));
    expect(await calls.find((call) => call.method === 'PATCH')!.json()).toEqual({
      polygon: { type: 'Polygon', coordinates: [[[69.2, 41.3], [69.35, 41.3], [69.3, 41.4], [69.2, 41.3]]] },
      free_delivery_from: null,
    });
  });

  it('closes without a request when nothing changed', async () => {
    const { calls } = base();
    const typing = userEvent.setup();
    renderPage(<ZonesView />);
    await typing.click(await screen.findByRole('button', { name: 'Markaz hududini ochish' }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.getByTestId('map')).toHaveAttribute('data-editing', 'no'));
    expect(calls.some((call) => call.method === 'PATCH')).toBe(false);
  });

  it('deletes after the confirmation and says what the server says if it refuses', async () => {
    let attempt = 0;
    const { calls } = base({ 'DELETE /api/v1/owner/delivery-zones/7': () => (++attempt === 1 ? refusal('Hududni hozir o\'chirib bo\'lmaydi.', 422) : ok(null)) });
    const typing = userEvent.setup();
    renderPage(<ZonesView />);
    await typing.click(await screen.findByRole('button', { name: 'Markaz hududini ochish' }));
    await typing.click(screen.getByRole('button', { name: "Hududni o'chirish" }));
    const dialog = within(await screen.findByRole('alertdialog'));
    expect(calls.some((call) => call.method === 'DELETE')).toBe(false);

    await typing.click(dialog.getByRole('button', { name: "O'chirish" }));
    expect(await dialog.findByRole('alert')).toHaveTextContent("Hududni hozir o'chirib bo'lmaydi.");
    await typing.click(dialog.getByRole('button', { name: "O'chirish" }));
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
    expect(screen.getByRole('status')).toHaveTextContent("Markaz o'chirildi.");
  });
});
