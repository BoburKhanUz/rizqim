import type { ReactElement } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import { StaffApiProvider } from '@rizqim/staff';
import { vi } from 'vitest';
import { I18nProvider } from '@/components/I18nProvider';
import { ApiConfigContext } from '@/lib/apiConfig';
import { getStaffApi } from '@/lib/session/client';
import type { Language } from '@/i18n';

export const API = 'http://api.test/api';

// A page inside the providers it needs (language, API address, query cache)
export function renderPage(ui: ReactElement, language: Language = 'uz') {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <I18nProvider language={language}>
      <ApiConfigContext.Provider value={{ apiBaseUrl: API }}>
        <StaffApiProvider api={getStaffApi(API)}>
          <QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>
        </StaffApiProvider>
      </ApiConfigContext.Provider>
    </I18nProvider>,
  );
}

type Handler = (request: Request) => Response | Promise<Response>;

export const json = (body: unknown, status = 200, headers: Record<string, string> = {}) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } });

// Replaces fetch with a router: "METHOD /path" → handler. An unlisted call fails the test loudly.
export function mockFetch(routes: Record<string, Handler>) {
  const calls: Request[] = [];
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const request = input instanceof Request ? input : new Request(new URL(String(input), 'http://localhost:3000'), init);
    calls.push(request.clone());
    const key = `${request.method} ${new URL(request.url).pathname}`;
    const handler = routes[key];
    if (!handler) throw new Error(`Unexpected request: ${key}`);
    return handler(request);
  });
  vi.stubGlobal('fetch', fetchMock);
  return { fetchMock, calls };
}
