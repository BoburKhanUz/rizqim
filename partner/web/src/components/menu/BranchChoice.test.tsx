import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { BatchesView } from './BatchesView';
import { BranchOverrides } from './BranchOverrides';
import { StopListView } from './StopListView';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { batch, branch, group, ok, permissionsAnswer, product } from './menuFixtures';

// The stop-list, the batches and a product's branch variants work on the branch chosen in the top bar: never on "the
// first branch" while the panel shows another one.

const scope = vi.hoisted(() => ({ branchId: null as number | null }));
vi.mock('../shell/BranchScope', () => ({ useBranchScope: () => ({ branchId: scope.branchId }) }));
vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }) }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => {
  setSession(null);
  scope.branchId = null;
});

const branchOf = (request: Request) => Number(new URL(request.url).searchParams.get('branch_id'));
const branchesAsked = (calls: Request[], path: string) => calls.filter((call) => call.method === 'GET' && new URL(call.url).pathname.endsWith(path)).map(branchOf);

function routes(extra: Record<string, (request: Request) => Response | Promise<Response>> = {}) {
  return {
    'GET /api/v1/me/permissions': () => permissionsAnswer(),
    'GET /api/v1/owner/branches': () => ok([branch(), branch({ id: 2, name: 'Yunusobod' })]),
    'GET /api/v1/owner/stop-list': (request: Request) => ok({ branch_id: branchOf(request), entries: [] }),
    'GET /api/v1/owner/batches': (request: Request) => ok({ branch_id: branchOf(request), batches: [batch({ branch_id: branchOf(request) })] }),
    'GET /api/v1/owner/products': () => json({ success: true, data: [product()] }),
    'GET /api/v1/owner/modifier-groups': () => ok([group()]),
    'GET /api/v1/owner/employees': () => json({ success: true, data: [] }),
    'GET /api/v1/owner/branch-variants': () =>
      ok([{ product_id: 7, name: 'Osh', image_url: null, variants: [{ variant_id: 11, variant_uuid: 'v', name: '1 porsiya', price: '35000.00', is_active: true, hidden_reason: null, sort_order: 0 }] }]),
    ...extra,
  };
}

describe('the branch of the stock screens', () => {
  it('stop-list: the top bar\'s branch, named, with no picker of its own', async () => {
    scope.branchId = 2;
    const { calls } = mockFetch(routes());
    renderPage(<StopListView />);
    expect(await screen.findByText(/Filial: Yunusobod/)).toBeInTheDocument();
    expect(screen.queryByRole('combobox', { name: 'Filial' })).not.toBeInTheDocument();
    await waitFor(() => expect(branchesAsked(calls, '/stop-list')).toEqual([2]));
  });

  it('stop-list: with "all branches" in the top bar, the page asks which branch (first one to start with)', async () => {
    const { calls } = mockFetch(routes());
    renderPage(<StopListView />);
    const picker = await screen.findByRole('combobox', { name: 'Filial' });
    expect(picker).toHaveValue('1');
    await userEvent.setup().selectOptions(picker, '2');
    await waitFor(() => expect(branchesAsked(calls, '/stop-list')).toEqual([1, 2]));
  });

  it('batches: the top bar\'s branch only, without the overview of every branch', async () => {
    scope.branchId = 2;
    const { calls } = mockFetch(routes());
    renderPage(<BatchesView />);
    expect(await screen.findByText(/Filial: Yunusobod/)).toBeInTheDocument();
    await waitFor(() => expect(branchesAsked(calls, '/batches')).toContain(2));
    expect(branchesAsked(calls, '/batches')).not.toContain(1);
  });

  it('branch variants of a product: switched off in the top bar\'s branch', async () => {
    scope.branchId = 2;
    const { calls } = mockFetch(routes({ 'POST /api/v1/owner/branch-variants': () => ok({ id: 1 }) }));
    renderPage(<BranchOverrides product={product() as never} canManage />);
    const user = userEvent.setup();
    expect(await screen.findByText(/Filial: Yunusobod/)).toBeInTheDocument();
    await user.click(await screen.findByRole('switch', { name: '1 porsiya: filialda sotiladi' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    expect(await calls.find((call) => call.method === 'POST')!.json()).toMatchObject({ branch_id: 2, product_variant_id: 11, is_active: false });
    expect(new Set(branchesAsked(calls, '/branch-variants'))).toEqual(new Set([2]));
  });
});
