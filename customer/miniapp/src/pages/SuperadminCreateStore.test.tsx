import { act, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { MapPickerProps } from '../components/map/MapPicker';
import { MainButtonHost } from '../components/ui/MainButtonHost';
import { showAlert } from '../lib/dialogs';
import { callsTo, stubStaffApi, success, type FakeCall } from '../test/staffFetch';
import SuperadminCreateStore from './SuperadminCreateStore';

// The form that creates a store with its first branch. The branch is routed to by its coordinates, so the form must
// not send any the superadmin did not pick.

// Leaflet needs a real browser: the map is replaced by a button that "taps" a point
vi.mock('../components/map/LazyMapPicker', () => ({
  default: ({ position, onPick }: MapPickerProps) => (
    <div data-testid="map" data-pin={position ? `${position.lat},${position.lng}` : ''}>
      <button type="button" onClick={() => onPick({ lat: 41.2, lng: 69.1 })}>tap the map</button>
    </div>
  ),
}));
vi.mock('../lib/dialogs', () => ({ showAlert: vi.fn().mockResolvedValue(undefined) }));
vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: {} }));
vi.mock('../features/address/geocodeApi', () => ({ reverseGeocode: vi.fn().mockResolvedValue(null), searchAddress: vi.fn() }));

let calls: FakeCall[];
const posts = () => callsTo(calls, '/superadmin/stores', 'POST');

// The map's old default: Tashkent's centre, which used to be sent whenever the pin was left alone
const OLD_DEFAULT = { branch_lat: 41.311081, branch_lng: 69.240562 };

// Lets the (fake) network answer and React render what it brought
const flush = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });

async function renderForm() {
  ({ calls } = stubStaffApi((call) =>
    call.method === 'GET'
      ? success({ regions: [{ id: 1, name: 'Toshkent' }], districts: [{ id: 10, name: 'Chilonzor', region_id: 1 }] })
      : success({ store_id: 7, invite_link: 'https://t.me/bot?start=invite_x' }),
  ));
  const view = render(
    <MemoryRouter>
      <SuperadminCreateStore />
      <MainButtonHost />
    </MemoryRouter>,
  );
  await flush();
  return view;
}

const type = (placeholder: string, value: string) =>
  fireEvent.change(screen.getByPlaceholderText(placeholder), { target: { value } });
const typeAddress = (value: string) => fireEvent.change(screen.getByLabelText('Manzil'), { target: { value } });
const fillRequiredFields = () => {
  type('Masalan: Evos', 'Evos');
  type('Masalan: Alisher Valiyev', 'Alisher Valiyev');
  typeAddress('Chilonzor 12');
};
const submit = () => fireEvent.click(screen.getByRole('button', { name: /Restoranni saqlash/ }));
const tapMap = () => fireEvent.click(screen.getByRole('button', { name: 'tap the map' }));

beforeEach(() => {
  vi.mocked(showAlert).mockClear();
});

describe('SuperadminCreateStore: the branch location', () => {
  it('starts with no pin and loads no third-party map script', async () => {
    await renderForm();

    expect(screen.getByTestId('map').dataset.pin).toBe('');
    expect(document.querySelector('script[src*="yandex"]')).toBeNull();
    expect(document.getElementById('yandex-maps-js')).toBeNull();
    expect((window as unknown as { ymaps?: unknown }).ymaps).toBeUndefined();
  });

  it('does not create the store until the pin is placed, and says so', async () => {
    await renderForm();
    fillRequiredFields();

    submit();
    await flush();

    expect(posts()).toHaveLength(0);
    expect(showAlert).toHaveBeenCalledWith(expect.stringContaining('xaritada belgilanmagan'));
    expect(screen.getByRole('alert')).toHaveTextContent('Restoran joylashuvini xaritada belgilang');
  });

  it('sends the point the superadmin picked, and not the old Tashkent default', async () => {
    await renderForm();
    fillRequiredFields();
    tapMap();

    expect(screen.getByTestId('map').dataset.pin).toBe('41.2,69.1');
    submit();
    await flush();

    expect(posts()).toHaveLength(1);
    const { body } = posts()[0]!;
    expect(body).toMatchObject({
      name: 'Evos',
      owner_name: 'Alisher Valiyev',
      branch_address: 'Chilonzor 12',
      branch_lat: 41.2,
      branch_lng: 69.1,
      branch_region_id: '1',
      branch_district_id: '10',
    });
    expect(body).not.toMatchObject(OLD_DEFAULT);
    expect(showAlert).not.toHaveBeenCalledWith(expect.stringContaining('xaritada belgilanmagan'));
  });

  it('drops the pin error once the pin is placed', async () => {
    await renderForm();
    fillRequiredFields();
    submit();
    await flush();
    expect(screen.getByRole('alert')).toBeInTheDocument();

    tapMap();

    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('asks for the missing fields first when the address is only blanks', async () => {
    await renderForm();
    fillRequiredFields();
    typeAddress('   ');
    tapMap();

    submit();
    await flush();

    expect(posts()).toHaveLength(0);
    expect(showAlert).toHaveBeenCalledWith("Iltimos barcha majburiy maydonlarni to'ldiring.");
  });
});
