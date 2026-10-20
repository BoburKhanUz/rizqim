import { render, screen } from '@testing-library/react';
import { Suspense, type ComponentType } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { RouteErrorBoundary, RouteFallback } from '../components/Feedback';
import { lazyWithRetry } from './lazyWithRetry';

const Page = () => <p>Sahifa yuklandi</p>;
const SLOW = { timeout: 3000 };

function renderLazy(load: () => Promise<{ default: ComponentType }>) {
  const LazyPage = lazyWithRetry(load);
  return render(
    <RouteErrorBoundary>
      <Suspense fallback={<RouteFallback />}>
        <LazyPage />
      </Suspense>
    </RouteErrorBoundary>,
  );
}

beforeEach(() => {
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

describe('lazyWithRetry', () => {
  it('shows the light fallback while the page downloads, then the page', async () => {
    const load = vi.fn(() => Promise.resolve({ default: Page }));

    renderLazy(load);

    expect(screen.getByRole('status')).toBeInTheDocument();
    expect(await screen.findByText('Sahifa yuklandi')).toBeInTheDocument();
    expect(load).toHaveBeenCalledTimes(1);
  });

  it('tries again after a failed download and shows the page when the second try works', async () => {
    const load = vi
      .fn<() => Promise<{ default: ComponentType }>>()
      .mockRejectedValueOnce(new TypeError('Failed to fetch dynamically imported module'))
      .mockResolvedValueOnce({ default: Page });

    renderLazy(load);

    expect(await screen.findByText('Sahifa yuklandi', undefined, SLOW)).toBeInTheDocument();
    expect(load).toHaveBeenCalledTimes(2);
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('gives up after the second failure: the error boundary offers a reload', async () => {
    const load = vi
      .fn<() => Promise<{ default: ComponentType }>>()
      .mockRejectedValue(new TypeError('Failed to fetch dynamically imported module'));

    renderLazy(load);

    expect(await screen.findByRole('alert', undefined, SLOW)).toHaveTextContent("Sahifani yuklab bo'lmadi");
    expect(load).toHaveBeenCalledTimes(2);
  });
});
