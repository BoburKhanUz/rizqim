import type { ReactElement } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import { vi } from 'vitest';
import { AuthProvider } from '@/components/AuthProvider';
import { I18nProvider } from '@/components/I18nProvider';
import { StepUpProvider } from '@/components/StepUpProvider';

export const ADMIN = '/api/backend/v1/admin';

// The member of a test: the permissions the backend would list ("admin.<name>")
export const ALL_PERMISSIONS = [
  'today.view', 'orders.view', 'orders.intervene', 'stores.view', 'stores.manage', 'finance.view', 'subscriptions.manage', 'devices.view',
  'monitoring.view', 'support.view', 'users.view', 'marketing.view', 'marketing.manage', 'integrations.view', 'team.manage', 'audit.view',
].map((name) => `admin.${name}`);

export function meBody(overrides: Record<string, unknown> = {}) {
  return {
    success: true,
    message: '',
    data: {
      member: { id: 1, name: 'Aziza Karimova', email: 'aziza@rizqim.uz', platform_role: 'superadmin' },
      platform_role: 'superadmin',
      permissions: ALL_PERMISSIONS,
      two_factor_enabled: true,
      recovery_codes_left: 8,
      last_login_at: '2026-10-26T08:30:00+05:00',
      ...overrides,
    },
  };
}

type Handler = (request: Request) => Response | Promise<Response>;

export const json = (body: unknown, status = 200, headers: Record<string, string> = {}) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } });

export const ok = (data: unknown, extra: Record<string, unknown> = {}) => json({ success: true, message: '', data, ...extra });
export const refusal = (status: number, message: string, data: Record<string, unknown> = {}, extra: Record<string, unknown> = {}) =>
  json({ success: false, message, data, ...extra }, status);

export interface Call {
  method: string;
  path: string;
  query: URLSearchParams;
  body: unknown;
}

// Replaces fetch with a router: "METHOD /path" (the path below /api/backend/v1/admin) → handler. An unlisted call fails the test loudly.
export function mockFetch(routes: Record<string, Handler>) {
  const calls: Call[] = [];
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const request = input instanceof Request ? input : new Request(new URL(String(input), 'http://localhost:3000'), init);
    const url = new URL(request.url);
    const method = request.method;
    const text = method === 'GET' ? '' : await request.clone().text();
    calls.push({ method, path: url.pathname.replace(ADMIN, ''), query: url.searchParams, body: text ? JSON.parse(text) : null });
    const key = `${method} ${url.pathname.replace(ADMIN, '')}`;
    const handler = routes[key];
    if (!handler) throw new Error(`Unexpected request: ${key}`);
    return handler(request);
  });
  vi.stubGlobal('fetch', fetchMock);
  return { fetchMock, calls, callsTo: (method: string, path: string) => calls.filter((call) => call.method === method && call.path === path) };
}

// A page inside the providers it needs: language, the query cache, the step-up dialog and the session (GET /auth/me must be routed)
export function renderPage(ui: ReactElement) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <I18nProvider language="uz">
      <QueryClientProvider client={queryClient}>
        <StepUpProvider>
          <AuthProvider>{ui}</AuthProvider>
        </StepUpProvider>
      </QueryClientProvider>
    </I18nProvider>,
  );
}

// P4-31: an Excel export answered by the backend, and the browser's file saving replaced by a list of the names saved
export const XLSX_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

export const xlsx = (rows = 2, truncated = false) =>
  new Response(new Uint8Array([80, 75, 3, 4]), {
    status: 200,
    headers: { 'Content-Type': XLSX_TYPE, 'Content-Disposition': 'attachment; filename=rizqim-test.xlsx', 'X-Export-Rows': String(rows), 'X-Export-Truncated': truncated ? '1' : '0' },
  });

export function stubFileSaving(): string[] {
  const saved: string[] = [];
  vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: vi.fn(() => 'blob:test'), revokeObjectURL: vi.fn() }));
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
    saved.push(this.download);
  });
  return saved;
}
