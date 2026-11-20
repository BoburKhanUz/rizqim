import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { json, meBody, mockFetch, ok, renderPage } from '@/test/helpers';
import { deviceRow, devicePage } from './fixtures';
import { DevicesTab } from './DevicesTab';

// Wave M5 S3-d: the device row and card show what the signatures of the till's offline operations were worth (7 days),
// the number of its signing key and the store's mode. Never the key.

const ME = { 'GET /auth/me': () => json(meBody({ permissions: ['admin.devices.view'] })) };
const DEVICE = '7a1b0000-0000-4000-8000-000000000001';
const sync = deviceRow().sync;

describe('DevicesTab: queue signatures', () => {
  it('marks a till with badly signed or lost operations in the list, and only that one', async () => {
    const rows = [
      deviceRow({ sync: { ...sync, signatures: { days: 7, ok: 40, missing: 0, bad: 2, chain_gap: 1, key_id: 1, enforce: false } } }),
      deviceRow({ uuid: 'u2', name: 'Kassa 2', sync: { ...sync, signatures: { days: 7, ok: 12, missing: 5, bad: 0, chain_gap: 0, key_id: 1, enforce: false } } }),
      // An older server: no signatures in the row
      deviceRow({ uuid: 'u3', name: 'Kassa 3' }),
    ];
    mockFetch({ ...ME, 'GET /pos/devices': () => ok(devicePage(rows)) });
    renderPage(<DevicesTab selected={null} onSelect={() => {}} />);

    const list = await screen.findByRole('list', { name: "Qurilmalar ro'yxati" });
    const first = within(list).getByRole('button', { name: 'Kassa 1 qurilmasini ochish' });
    expect(within(first).getByText("Imzo noto'g'ri: 2")).toBeInTheDocument();
    expect(within(first).getByText("Navbatdan yo'qolgan: 1")).toBeInTheDocument();
    const second = within(list).getByRole('button', { name: 'Kassa 2 qurilmasini ochish' });
    expect(within(second).queryByText(/Imzo noto'g'ri/)).not.toBeInTheDocument();
    expect(within(second).getByText('Imzosiz: 5')).toBeInTheDocument();
    const third = within(list).getByRole('button', { name: 'Kassa 3 qurilmasini ochish' });
    expect(within(third).queryByText(/Imzo/)).not.toBeInTheDocument();
  });

  it('shows the counts, the key number and the mode on the card', async () => {
    mockFetch({ ...ME, [`GET /pos/devices/${DEVICE}`]: () => ok(deviceRow({ sync: { ...sync, signatures: { days: 7, ok: 40, missing: 3, bad: 2, chain_gap: 1, key_id: 2, enforce: true } } })) });
    renderPage(<DevicesTab selected={DEVICE} onSelect={() => {}} />);

    const facts = await screen.findByRole('group', { name: 'Navbat imzosi (oxirgi 7 kun)' });
    const value = (name: string) => within(facts).getByText(name).nextElementSibling?.textContent;
    expect(value("To'g'ri imzolangan")).toBe('40');
    expect(value('Imzosiz')).toBe('3');
    expect(value("Imzosi noto'g'ri")).toBe('2');
    expect(value("Navbatdan yo'qolgan (uzilish)")).toBe('1');
    expect(value('Kalit raqami')).toBe('2');
    expect(value("Do'kon rejimi")).toBe("Qat'iy");
  });

  it('says so when the till has no key, and shows no section for an older server', async () => {
    mockFetch({ ...ME, [`GET /pos/devices/${DEVICE}`]: () => ok(deviceRow({ sync: { ...sync, signatures: { days: 7, ok: 0, missing: 0, bad: 0, chain_gap: 0, key_id: null, enforce: false } } })) });
    const first = renderPage(<DevicesTab selected={DEVICE} onSelect={() => {}} />);
    const facts = await screen.findByRole('group', { name: 'Navbat imzosi (oxirgi 7 kun)' });
    expect(within(facts).getByText('Kalit raqami').nextElementSibling?.textContent).toBe("kalit yo'q");
    expect(within(facts).getByText("Do'kon rejimi").nextElementSibling?.textContent).toBe('Kuzatish');
    first.unmount();

    mockFetch({ ...ME, [`GET /pos/devices/${DEVICE}`]: () => ok(deviceRow()) });
    renderPage(<DevicesTab selected={DEVICE} onSelect={() => {}} />);
    await screen.findByRole('heading', { name: 'Kassa 1' });
    expect(screen.queryByRole('group', { name: /Navbat imzosi/ })).not.toBeInTheDocument();
  });
});
