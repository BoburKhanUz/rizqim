import { act, render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { makeStore, makeUser } from '../test/factories';
import CustomerProfile from './CustomerProfile';

// The profile refreshes the signed-in user from GET /user: the cashback balance must stay a number in the store

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: {} }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);

const flush = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });

beforeEach(() => {
  useAuthStore.getState().setAuth('token', makeUser({ balance: '0.00' }), makeStore(), false);
  get.mockImplementation((url) => {
    if (url === '/user') {
      return Promise.resolve({
        data: { success: true, data: { user: makeUser({ balance: '125000.00', name: 'Yangi Ism' }), store: null, sms_verification_required: false } },
      });
    }
    return Promise.resolve({ data: { success: true, data: url === '/locations' ? { regions: [], districts: [] } : [] } });
  });
});

describe('CustomerProfile: refreshing the user', () => {
  it('keeps the balance a number, not the decimal string the API sent', async () => {
    render(
      <MemoryRouter>
        <CustomerProfile />
      </MemoryRouter>,
    );
    await flush();

    const { user } = useAuthStore.getState();
    expect(user?.name).toBe('Yangi Ism');
    expect(user?.balance).toBe(125000);
    expect(typeof user?.balance).toBe('number');
  });
});
