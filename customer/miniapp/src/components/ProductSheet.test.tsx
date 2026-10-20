import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { makeProduct, makeVariant } from '../test/factories';
import type { Product, ProductVariant } from '../types';
import VariantSelectorModal from './VariantSelectorModal';

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));

const small = makeVariant({ id: 1, uuid: 'v-small', name: 'Kichik', price: '18000.00', stock: 5 });
const large = makeVariant({ id: 2, uuid: 'v-large', name: 'Katta', price: '32000.00', stock: 3 });

function renderModal(product: Product | null, handlers: { onAdd?: (v: ProductVariant) => void; onClose?: () => void } = {}) {
  const onAdd = handlers.onAdd ?? vi.fn();
  const onClose = handlers.onClose ?? vi.fn();
  const view = render(<VariantSelectorModal isOpen product={product} onAdd={onAdd} onClose={onClose} />);
  return { onAdd, onClose, ...view };
}

const confirmButton = () => screen.getByRole('button', { name: /Tasdiqlash|Variant tanlang/ });

describe('VariantSelectorModal', () => {
  it('renders nothing while closed or without a product', () => {
    const { rerender } = render(<VariantSelectorModal isOpen={false} product={makeProduct({ variants: [small] })} onAdd={vi.fn()} onClose={vi.fn()} />);
    expect(screen.queryByText('Kichik')).not.toBeInTheDocument();

    rerender(<VariantSelectorModal isOpen product={null} onAdd={vi.fn()} onClose={vi.fn()} />);
    expect(screen.queryByText('Kichik')).not.toBeInTheDocument();
  });

  it('lists the variants with their prices and starts on the first one', () => {
    renderModal(makeProduct({ name: 'Lavash', variants: [small, large] }));

    expect(screen.getByText('Lavash')).toBeInTheDocument();
    expect(screen.getByText('Kichik')).toBeInTheDocument();
    expect(screen.getByText('Katta')).toBeInTheDocument();
    expect(screen.getByText("32 000 so'm")).toBeInTheDocument();
    expect(confirmButton()).toHaveTextContent("Tasdiqlash · 18 000 so'm");
    expect(confirmButton()).toBeEnabled();
  });

  it('shows the cheapest variant as the starting price', () => {
    renderModal(makeProduct({ variants: [large, small] }));

    expect(screen.getByText(/Asosiy narx/)).toHaveTextContent("Asosiy narx: 18 000 so'm");
  });

  describe('out of stock', () => {
    const soldOut = makeVariant({ id: 3, uuid: 'v-sold-out', name: 'Oilaviy', price: '60000.00', stock: 0 });

    it('marks a sold-out variant as "Tugagan"', () => {
      renderModal(makeProduct({ variants: [small, soldOut] }));

      const label = screen.getByText('Tugagan');
      expect(label).toBeInTheDocument();
      expect(screen.getByRole('radio', { name: /Oilaviy/ })).toBeDisabled();
      expect(screen.getByRole('radio', { name: /Kichik/ })).toBeEnabled();
    });

    it('does not preselect a sold-out first variant: it starts on the first one that can be ordered', () => {
      renderModal(makeProduct({ variants: [soldOut, large] }));

      expect(confirmButton()).toHaveTextContent("Tasdiqlash · 32 000 so'm");
    });

    it('ignores a click on a sold-out variant', () => {
      const { onAdd } = renderModal(makeProduct({ variants: [small, soldOut] }));

      fireEvent.click(screen.getByText('Oilaviy'));

      expect(confirmButton()).toHaveTextContent("Tasdiqlash · 18 000 so'm"); // still the small one
      fireEvent.click(confirmButton());
      expect(onAdd).toHaveBeenCalledWith(small);
    });

    it('cannot add anything when every variant is sold out', () => {
      const alsoSoldOut = makeVariant({ id: 4, uuid: 'v-also', name: 'Mini', price: '9000.00', stock: -1 });
      const { onAdd, onClose } = renderModal(makeProduct({ variants: [soldOut, alsoSoldOut] }));

      expect(screen.getAllByText('Tugagan')).toHaveLength(2);
      expect(confirmButton()).toBeDisabled();
      expect(confirmButton()).toHaveTextContent('Variant tanlang');

      fireEvent.click(screen.getByText('Oilaviy'));
      fireEvent.click(confirmButton());

      expect(onAdd).not.toHaveBeenCalled();
      expect(onClose).not.toHaveBeenCalled();
    });

    it('treats a variant whose stock the API did not send as available', () => {
      const unknownStock = { ...small, stock: undefined } as unknown as ProductVariant;
      const { onAdd } = renderModal(makeProduct({ variants: [unknownStock] }));

      expect(screen.queryByText('Tugagan')).not.toBeInTheDocument();
      fireEvent.click(confirmButton());
      expect(onAdd).toHaveBeenCalledTimes(1);
    });
  });

  it('cannot select a variant the store switched off', () => {
    const switchedOff = makeVariant({ id: 5, uuid: 'v-off', name: 'Maxsus', price: '50000.00', stock: 9, is_active: false });
    const legacyOff = makeVariant({ id: 6, uuid: 'v-off0', name: 'Eski', price: '51000.00', stock: 9, is_active: 0 });
    renderModal(makeProduct({ variants: [small, switchedOff, legacyOff] }));

    fireEvent.click(screen.getByText('Maxsus'));
    fireEvent.click(screen.getByText('Eski'));

    expect(confirmButton()).toHaveTextContent("Tasdiqlash · 18 000 so'm");
  });

  it('switches the selection and adds the chosen variant, then closes', () => {
    const { onAdd, onClose } = renderModal(makeProduct({ variants: [small, large] }));

    fireEvent.click(screen.getByText('Katta'));
    expect(confirmButton()).toHaveTextContent("Tasdiqlash · 32 000 so'm");
    fireEvent.click(confirmButton());

    expect(onAdd).toHaveBeenCalledTimes(1);
    expect(onAdd).toHaveBeenCalledWith(large);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('closes from the backdrop without adding anything', () => {
    const { onAdd, onClose } = renderModal(makeProduct({ variants: [small] }));

    fireEvent.click(screen.getByRole('dialog').parentElement as HTMLElement); // the scrim around the sheet

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(onAdd).not.toHaveBeenCalled();
  });

  it('starts every product on its own default, not on the previous selection', () => {
    const lavash = makeProduct({ uuid: 'p-lavash', name: 'Lavash', variants: [small, large] });
    const shawarma = makeProduct({ uuid: 'p-shawarma', name: 'Shaurma', variants: [
      makeVariant({ id: 7, uuid: 'v-s1', name: 'Oddiy', price: '21000.00' }),
      makeVariant({ id: 8, uuid: 'v-s2', name: 'Pishloqli', price: '27000.00' }),
    ] });
    const { rerender, onAdd, onClose } = renderModal(lavash);
    fireEvent.click(screen.getByText('Katta'));

    rerender(<VariantSelectorModal isOpen product={shawarma} onAdd={onAdd} onClose={onClose} />);

    expect(confirmButton()).toHaveTextContent("Tasdiqlash · 21 000 so'm");
  });
});
