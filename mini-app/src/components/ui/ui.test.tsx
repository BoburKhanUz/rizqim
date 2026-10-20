import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Plus } from 'lucide-react';
import { MemoryRouter } from 'react-router-dom';
import { Button } from './Button';
import { IconButton } from './IconButton';
import { StatusBadge } from './StatusBadge';
import { InlineError } from './InlineError';
import { EmptyState } from './EmptyState';
import { Dialog } from './Dialog';
import { DialogHost } from './DialogHost';
import { MainButtonHost } from './MainButtonHost';
import { BottomDock } from './BottomDock';
import { showAlert, showConfirm } from '../../lib/dialogs';
import { useMainButton } from '../../hooks/useMainButton';
import { useLanguageStore } from '../../store/languageStore';

afterEach(() => useLanguageStore.setState({ language: 'uz' }));

describe('Button', () => {
  it('is a 44px button by default and 52px when large', () => {
    render(
      <>
        <Button>Saqlash</Button>
        <Button size="lg">Buyurtma berish</Button>
      </>,
    );
    expect(screen.getByRole('button', { name: 'Saqlash' })).toHaveClass('min-h-11', 'bg-brand', 'text-on-brand');
    expect(screen.getByRole('button', { name: 'Buyurtma berish' })).toHaveClass('min-h-13');
  });

  it('is busy and cannot be pressed while loading, and keeps its label', () => {
    const onClick = vi.fn();
    render(
      <Button loading onClick={onClick}>
        Yuborish
      </Button>,
    );
    const button = screen.getByRole('button', { name: 'Yuborish' });
    expect(button).toBeDisabled();
    expect(button).toHaveAttribute('aria-busy', 'true');
    fireEvent.click(button);
    expect(onClick).not.toHaveBeenCalled();
  });

  it('paints each variant with tokens', () => {
    render(
      <>
        <Button variant="danger">O'chirish</Button>
        <Button variant="success">Yetkazib berdim</Button>
        <Button variant="secondary">Bekor</Button>
      </>,
    );
    expect(screen.getByRole('button', { name: "O'chirish" })).toHaveClass('bg-danger', 'text-on-danger');
    expect(screen.getByRole('button', { name: 'Yetkazib berdim' })).toHaveClass('bg-success', 'text-on-success');
    expect(screen.getByRole('button', { name: 'Bekor' })).toHaveClass('bg-surface-2', 'text-fg');
  });
});

describe('IconButton', () => {
  it('has a name and a 44px target', () => {
    render(<IconButton icon={Plus} label="Qo'shish" />);
    expect(screen.getByRole('button', { name: "Qo'shish" })).toHaveClass('size-11');
  });
});

describe('StatusBadge', () => {
  it("says the customer's or the staff's word for the status", () => {
    render(
      <>
        <StatusBadge status="new" />
        <StatusBadge status="courier_assigned" audience="staff" />
      </>,
    );
    expect(screen.getByText('Kutilmoqda').parentElement).toHaveClass('bg-warning-soft', 'text-warning-soft-fg');
    expect(screen.getByText('Kuryer kutilmoqda').parentElement).toHaveClass('bg-info-soft');
  });

  it('follows the language', () => {
    render(<StatusBadge status="delivered" />);
    act(() => useLanguageStore.setState({ language: 'ru' }));
    expect(screen.getByText('Доставлен')).toBeInTheDocument();
  });

  it('does not pulse', () => {
    const { container } = render(<StatusBadge status="preparing" />);
    expect(container.innerHTML).not.toContain('animate-pulse');
  });
});

describe('InlineError', () => {
  it('says what happened in the user language and retries', () => {
    const onRetry = vi.fn();
    render(<InlineError onRetry={onRetry} />);
    expect(screen.getByRole('alert')).toHaveTextContent("Ma'lumotlarni yuklab bo'lmadi");
    fireEvent.click(screen.getByRole('button', { name: 'Qayta urinish' }));
    expect(onRetry).toHaveBeenCalledTimes(1);
  });

  it('has no retry button without onRetry', () => {
    render(<InlineError />);
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });
});

describe('EmptyState', () => {
  it('shows a title, a text and the action', () => {
    render(<EmptyState icon={Plus} title="Buyurtmalar yo'q" text="Hali buyurtma bermadingiz" action={<Button>Katalogga</Button>} />);
    expect(screen.getByRole('heading', { name: "Buyurtmalar yo'q" })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Katalogga' })).toBeInTheDocument();
  });
});

describe('Dialog', () => {
  it('is a named modal that closes with Escape and the close button', () => {
    const onClose = vi.fn();
    render(
      <Dialog open onClose={onClose} title="Manzil">
        <input aria-label="Ko'cha" />
      </Dialog>,
    );
    const dialog = screen.getByRole('dialog', { name: 'Manzil' });
    expect(dialog).toHaveAttribute('aria-modal', 'true');
    fireEvent.keyDown(document, { key: 'Escape' });
    fireEvent.click(screen.getByRole('button', { name: 'Yopish' }));
    expect(onClose).toHaveBeenCalledTimes(2);
  });

  it('cannot be closed while busy', () => {
    const onClose = vi.fn();
    render(<Dialog open busy onClose={onClose} title="Saqlanmoqda" />);
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onClose).not.toHaveBeenCalled();
  });
});

describe('showAlert / showConfirm outside Telegram', () => {
  it('asks in an in-app dialog, never window.confirm', async () => {
    const nativeConfirm = vi.spyOn(window, 'confirm');
    render(<DialogHost />);

    let answer: boolean | undefined;
    await act(async () => {
      void showConfirm("Mahsulot o'chirilsinmi?", { confirmText: "O'chirish", destructive: true }).then((value) => (answer = value));
    });
    const dialog = screen.getByRole('alertdialog', { name: "Mahsulot o'chirilsinmi?" });
    expect(screen.getByRole('button', { name: "O'chirish" })).toHaveClass('bg-danger');
    await act(async () => fireEvent.click(screen.getByRole('button', { name: "O'chirish" })));
    expect(answer).toBe(true);
    expect(dialog).not.toBeInTheDocument();

    await act(async () => {
      void showConfirm('Chiqilsinmi?').then((value) => (answer = value));
    });
    await act(async () => fireEvent.click(screen.getByRole('button', { name: 'Bekor qilish' })));
    expect(answer).toBe(false);
    expect(nativeConfirm).not.toHaveBeenCalled();
  });

  it('shows alerts one after another', async () => {
    render(<DialogHost />);
    await act(async () => {
      void showAlert('Birinchi');
      void showAlert('Ikkinchi');
    });
    expect(screen.getByRole('alertdialog', { name: 'Birinchi' })).toBeInTheDocument();
    await act(async () => fireEvent.click(screen.getByRole('button', { name: 'OK' })));
    expect(screen.getByRole('alertdialog', { name: 'Ikkinchi' })).toBeInTheDocument();
    await act(async () => fireEvent.click(screen.getByRole('button', { name: 'OK' })));
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
  });
});

describe('useMainButton outside Telegram', () => {
  function Screen({ onClick, enabled = true }: { onClick: () => void; enabled?: boolean }) {
    const { native } = useMainButton({ text: 'Buyurtma berish', onClick, enabled });
    return <p>{native ? 'native' : 'fallback'}</p>;
  }

  it('draws the button in the page and calls the screen back', () => {
    const onClick = vi.fn();
    const { unmount } = render(
      <MemoryRouter>
        <Screen onClick={onClick} />
        <MainButtonHost />
      </MemoryRouter>,
    );
    expect(screen.getByText('fallback')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Buyurtma berish' }));
    expect(onClick).toHaveBeenCalledTimes(1);
    unmount();
  });

  it('disables it and removes it when the screen goes away', () => {
    const { rerender } = render(
      <>
        <Screen onClick={() => {}} enabled={false} />
        <MainButtonHost />
      </>,
    );
    expect(screen.getByRole('button', { name: 'Buyurtma berish' })).toBeDisabled();
    rerender(<MainButtonHost />);
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });
});

describe('BottomDock', () => {
  it('publishes its height for the page padding and clears it when it goes', () => {
    const { unmount } = render(
      <BottomDock withNav={false}>
        <p>nav</p>
      </BottomDock>,
    );
    expect(document.documentElement.style.getPropertyValue('--rz-dock-height')).toMatch(/^\d+px$/);
    unmount();
    expect(document.documentElement.style.getPropertyValue('--rz-dock-height')).toBe('0px');
  });
});
