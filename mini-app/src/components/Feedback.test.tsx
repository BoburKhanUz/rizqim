import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { RouteErrorBoundary } from './Feedback';

function Broken(): never {
  throw new Error('Failed to fetch dynamically imported module: /assets/Orders-OLD.js');
}

beforeEach(() => {
  // React logs a caught render error; the output is noise here
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

describe('RouteErrorBoundary', () => {
  it('shows the page when nothing fails', () => {
    render(
      <RouteErrorBoundary>
        <p>Buyurtmalar</p>
      </RouteErrorBoundary>,
    );

    expect(screen.getByText('Buyurtmalar')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('replaces a page that fails to render by a message with a reload button, instead of a blank app', () => {
    render(
      <RouteErrorBoundary>
        <Broken />
      </RouteErrorBoundary>,
    );

    expect(screen.getByRole('alert')).toHaveTextContent("Sahifani yuklab bo'lmadi");
    expect(screen.getByRole('button', { name: 'Qayta yuklash' })).toBeInTheDocument();
  });

  it('reloads the page from the button', () => {
    const reload = vi.fn();
    vi.stubGlobal('location', { ...window.location, reload });
    render(
      <RouteErrorBoundary>
        <Broken />
      </RouteErrorBoundary>,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Qayta yuklash' }));

    expect(reload).toHaveBeenCalledTimes(1);
  });

  it('gives the next page a fresh start when the route changes', () => {
    const { rerender } = render(
      <RouteErrorBoundary resetKey="/orders">
        <Broken />
      </RouteErrorBoundary>,
    );
    expect(screen.getByRole('alert')).toBeInTheDocument();

    rerender(
      <RouteErrorBoundary resetKey="/profile">
        <p>Profil</p>
      </RouteErrorBoundary>,
    );

    expect(screen.getByText('Profil')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('stays on the error while the route is the same', () => {
    const { rerender } = render(
      <RouteErrorBoundary resetKey="/orders">
        <Broken />
      </RouteErrorBoundary>,
    );

    rerender(
      <RouteErrorBoundary resetKey="/orders">
        <p>Buyurtmalar</p>
      </RouteErrorBoundary>,
    );

    expect(screen.getByRole('alert')).toBeInTheDocument();
    expect(screen.queryByText('Buyurtmalar')).not.toBeInTheDocument();
  });
});
