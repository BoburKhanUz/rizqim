import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { tg } from '../lib/telegram';
import { useLanguageStore } from '../store/languageStore';
import { ErrorFallback, RouteErrorBoundary } from './Feedback';

vi.mock('../lib/telegram', () => ({ tg: { close: vi.fn(), initDataUnsafe: {} } }));

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

// The sign-in error screen. A reload keeps the launch's Telegram data, so when that data is too old to sign in with
// (lib/launch.ts) the screen asks to close the Mini App and open it again (WebApp.close()) instead of a reload that
// cannot work; any other failure keeps the reload retry.

const reload = vi.fn();

afterEach(() => {
  useLanguageStore.setState({ language: 'uz' });
  vi.mocked(tg.close).mockClear();
  reload.mockClear();
  vi.unstubAllGlobals();
});

function stubReload() {
  vi.stubGlobal('location', { ...window.location, reload });
}

describe('ErrorFallback', () => {
  it('a transient failure: the reload retry', () => {
    stubReload();
    render(<ErrorFallback error="Connection timeout." />);

    expect(screen.getByText('Kirish amalga oshmadi')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Qayta urinish' }));
    expect(reload).toHaveBeenCalledTimes(1);
    expect(tg.close).not.toHaveBeenCalled();
  });

  it.each([
    ['uz', 'Kirish muddati tugadi', 'Ilovani yopish'],
    ['ru', 'Время входа истекло', 'Закрыть приложение'],
    ['en', 'The sign-in has expired', 'Close the app'],
  ] as const)('launch data too old (%s): says so and closes the app to be reopened, with no reload', (language, title, button) => {
    stubReload();
    useLanguageStore.setState({ language });
    render(<ErrorFallback error="The Telegram launch data has expired" reopen />);

    expect(screen.getByRole('heading', { name: title })).toBeInTheDocument();
    expect(screen.getAllByRole('button')).toHaveLength(1);
    fireEvent.click(screen.getByRole('button', { name: button }));
    expect(tg.close).toHaveBeenCalledTimes(1);
    expect(reload).not.toHaveBeenCalled();
  });
});
