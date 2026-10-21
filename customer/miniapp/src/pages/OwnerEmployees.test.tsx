import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { stubStaffApi, success } from '../test/staffFetch';
import { useAuthStore } from '../store/authStore';
import { makeEmployee, makeStore, makeUser } from '../test/factories';
import type { Employee } from '../types';
import OwnerEmployees from './OwnerEmployees';

// The owner's staff list names the branch of each employee: "Filialsiz" is only for one that has none

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));

function serveEmployees(employees: Employee[]) {
  stubStaffApi(() => success(employees));
}

beforeEach(() => {
  useAuthStore.getState().setAuth('token', makeUser({ role: 'owner', name: 'Egasi' }), makeStore(), false);
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  useAuthStore.getState().logout();
});

describe('OwnerEmployees: the branch of each employee', () => {
  it('shows the name of the branch an employee works at', async () => {
    serveEmployees([
      makeEmployee({ id: 11, name: 'Bobur', role: 'manager', branch_id: 3, branch: { id: 3, name: 'Chilonzor' } }),
      makeEmployee({ id: 12, name: 'Sardor', role: 'courier', branch_id: 4, branch: { id: 4, name: 'Yunusobod' } }),
    ]);

    render(
      <MemoryRouter>
        <OwnerEmployees />
      </MemoryRouter>,
    );

    expect(await screen.findByText('Menejer • Chilonzor')).toBeInTheDocument();
    expect(screen.getByText('Kuryer • Yunusobod')).toBeInTheDocument();
    expect(screen.queryByText(/Filialsiz/)).not.toBeInTheDocument();
  });

  it('says "Filialsiz" for an employee whose branch is null, and for that one only', async () => {
    serveEmployees([
      makeEmployee({ id: 11, name: 'Bobur', role: 'manager', branch_id: 3, branch: { id: 3, name: 'Chilonzor' } }),
      makeEmployee({ id: 12, name: 'Sardor', role: 'courier', branch_id: null, branch: null }),
    ]);

    render(
      <MemoryRouter>
        <OwnerEmployees />
      </MemoryRouter>,
    );

    expect(await screen.findByText('Kuryer • Filialsiz')).toBeInTheDocument();
    expect(screen.getByText('Menejer • Chilonzor')).toBeInTheDocument();
    expect(screen.getAllByText(/Filialsiz/)).toHaveLength(1);
  });

  it('does not claim "Filialsiz" when the response simply does not say which branch it is', async () => {
    // A backend that does not embed the branch (yet): `branch` is missing, not null
    const employee = makeEmployee({ id: 11, name: 'Bobur', role: 'manager', branch_id: 3 });
    delete employee.branch;
    serveEmployees([employee]);

    render(
      <MemoryRouter>
        <OwnerEmployees />
      </MemoryRouter>,
    );

    expect(await screen.findByText('Bobur')).toBeInTheDocument();
    expect(screen.getByText('Menejer', { selector: 'p' })).toBeInTheDocument(); // the role, with no branch after it
    expect(screen.queryByText(/Filialsiz/)).not.toBeInTheDocument();
  });

  it('marks an invited employee who has not joined yet', async () => {
    serveEmployees([
      makeEmployee({ id: 11, name: 'Bobur', role: 'courier', branch: { id: 1, name: 'Chilonzor' }, telegram_invite_token: 'emp_abc' }),
    ]);

    render(
      <MemoryRouter>
        <OwnerEmployees />
      </MemoryRouter>,
    );

    expect(await screen.findByText('Kuryer • Chilonzor • (Kutmoqda)')).toBeInTheDocument();
  });
});
