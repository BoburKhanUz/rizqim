import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { makeProduct, makeVariant } from '../test/factories';
import { availabilityView } from '../lib/menuAvailability';
import { useCartStore } from '../store/cartStore';
import type { Modifier, ModifierGroup, Product, ProductVariant } from '../types';
import type { ProductChoice } from './ProductSheet';
import ProductSheet from './ProductSheet';

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));

const small = makeVariant({ id: 1, uuid: 'v-small', name: 'Kichik', price: '18000.00', stock: 5 });
const large = makeVariant({ id: 2, uuid: 'v-large', name: 'Katta', price: '32000.00', stock: 3 });

function renderModal(product: Product | null, handlers: { onAdd?: (choice: ProductChoice) => void; onClose?: () => void } = {}) {
  const onAdd = handlers.onAdd ?? vi.fn();
  const onClose = handlers.onClose ?? vi.fn();
  const view = render(<ProductSheet isOpen product={product} onAdd={onAdd} onClose={onClose} />);
  return { onAdd, onClose, ...view };
}

const confirmButton = () => screen.getByRole('button', { name: /Tasdiqlash|Variant tanlang|Tugadi|tanlang|hozir mavjud emas/ });

describe('ProductSheet', () => {
  it('renders nothing while closed or without a product', () => {
    const { rerender } = render(<ProductSheet isOpen={false} product={makeProduct({ variants: [small] })} onAdd={vi.fn()} onClose={vi.fn()} />);
    expect(screen.queryByText('Kichik')).not.toBeInTheDocument();

    rerender(<ProductSheet isOpen product={null} onAdd={vi.fn()} onClose={vi.fn()} />);
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

      const label = screen.getByText('Tugadi');
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
      expect(onAdd).toHaveBeenCalledWith(expect.objectContaining({ variant: small, quantity: 1 }));
    });

    it('cannot add anything when every variant is sold out', () => {
      const alsoSoldOut = makeVariant({ id: 4, uuid: 'v-also', name: 'Mini', price: '9000.00', stock: -1 });
      const { onAdd, onClose } = renderModal(makeProduct({ variants: [soldOut, alsoSoldOut] }));

      expect(screen.getAllByText('Tugadi')).toHaveLength(3); // both variants, and the button
      expect(confirmButton()).toBeDisabled();
      expect(confirmButton()).toHaveTextContent('Tugadi');

      fireEvent.click(screen.getByText('Oilaviy'));
      fireEvent.click(confirmButton());

      expect(onAdd).not.toHaveBeenCalled();
      expect(onClose).not.toHaveBeenCalled();
    });

    it('treats a variant whose stock the API did not send as available', () => {
      const unknownStock = { ...small, stock: undefined } as unknown as ProductVariant;
      const { onAdd } = renderModal(makeProduct({ variants: [unknownStock] }));

      expect(screen.queryByText('Tugadi')).not.toBeInTheDocument();
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
    expect(onAdd).toHaveBeenCalledWith(expect.objectContaining({ variant: large, quantity: 1, modifiers: [] }));
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

    rerender(<ProductSheet isOpen product={shawarma} onAdd={onAdd} onClose={onClose} />);

    expect(confirmButton()).toHaveTextContent("Tasdiqlash · 21 000 so'm");
  });
});

// --- Modifiers and weight (P1-31) ---

// toHaveTextContent compares with the whitespace of the page collapsed to single spaces
const som = (amount: string) => `${amount} so'm`;

function modifier(id: number, name: string, delta: string, extra: Partial<Modifier> = {}): Modifier {
  return { id, uuid: `m-${id}`, modifier_group_id: 1, name, price_delta: delta, max_quantity: 1, sort_order: id, is_active: true, ...extra };
}

const sauce: ModifierGroup = {
  id: 1, uuid: 'g-1', name: 'Sous', min_select: 1, max_select: 1, is_required: true, sort_order: 0,
  modifiers: [modifier(1, 'Ketchup', '0.00'), modifier(2, 'Pishloqli', '3000.00')],
};
const extras: ModifierGroup = {
  id: 2, uuid: 'g-2', name: "Qo'shimchalar", min_select: 0, max_select: 2, is_required: false, sort_order: 1,
  modifiers: [modifier(3, 'Pishloq', '2000.00', { modifier_group_id: 2 }), modifier(4, "Go'sht", '9000.00', { modifier_group_id: 2 }), modifier(5, 'Zaytun', '1000.00', { modifier_group_id: 2 })],
};
const burger = (groups: ModifierGroup[]) =>
  makeProduct({ name: 'Burger', variants: [makeVariant({ id: 1, uuid: 'v-burger', name: 'Katta', price: '50000.00', stock: 30 })], modifier_groups: groups });

beforeEach(() => {
  useCartStore.setState({ items: [], storeName: '', storeAddress: '' });
});

describe('modifier groups', () => {
  it('cannot be confirmed before the required group is chosen, and says which one', () => {
    renderModal(burger([sauce]));

    expect(confirmButton()).toBeDisabled();
    expect(confirmButton()).toHaveTextContent('"Sous" tanlang');
    expect(screen.getByText('Majburiy · 1 ta tanlang')).toBeInTheDocument();
  });

  it('shows the running price and adds the chosen modifier once the required group is chosen', () => {
    const { onAdd } = renderModal(burger([sauce]));

    fireEvent.click(screen.getByRole('radio', { name: /Pishloqli/ }));

    expect(confirmButton()).toBeEnabled();
    expect(confirmButton()).toHaveTextContent(`Tasdiqlash · ${som('53 000')}`);

    fireEvent.click(confirmButton());
    expect(onAdd).toHaveBeenCalledWith({
      variant: expect.objectContaining({ uuid: 'v-burger' }),
      quantity: 1,
      modifiers: [{ id: 2, uuid: 'm-2', groupId: 1, name: 'Pishloqli', priceDelta: 3000, quantity: 1 }],
    });
  });

  it('lets one choice replace another in a choose-one group', () => {
    renderModal(burger([sauce]));

    fireEvent.click(screen.getByRole('radio', { name: /Pishloqli/ }));
    fireEvent.click(screen.getByRole('radio', { name: /Ketchup/ }));

    expect(screen.getByRole('radio', { name: /Ketchup/ })).toBeChecked();
    expect(screen.getByRole('radio', { name: /Pishloqli/ })).not.toBeChecked();
    expect(confirmButton()).toHaveTextContent(`Tasdiqlash · ${som('50 000')}`);
  });

  it('lets an optional group stay empty, and stops at its maximum', () => {
    renderModal(burger([extras]));
    expect(confirmButton()).toBeEnabled();

    fireEvent.click(screen.getByRole('checkbox', { name: /Pishloq/ }));
    fireEvent.click(screen.getByRole('checkbox', { name: /Go'sht/ }));
    fireEvent.click(screen.getByRole('checkbox', { name: /Zaytun/ })); // the group takes two

    expect(screen.getByRole('checkbox', { name: /Zaytun/ })).not.toBeChecked();
    expect(confirmButton()).toHaveTextContent(`Tasdiqlash · ${som('61 000')}`);
  });

  it('multiplies the price by the number of pieces: the modifiers are on every piece', () => {
    const { onAdd } = renderModal(burger([sauce]));

    fireEvent.click(screen.getByRole('radio', { name: /Pishloqli/ }));
    fireEvent.click(screen.getByRole('button', { name: 'Burger: ko\'paytirish' }));

    expect(confirmButton()).toHaveTextContent(`Tasdiqlash · ${som('106 000')}`);
    fireEvent.click(confirmButton());
    expect(onAdd).toHaveBeenCalledWith(expect.objectContaining({ quantity: 2 }));
  });

  it('shows a stopped modifier as "Tugadi" and cannot choose it', () => {
    const view = availabilityView([{ branch_id: 1, stopped: { products: [], variants: [], modifiers: [2] }, sold_out: { variants: [] } }]);
    render(<ProductSheet isOpen product={burger([sauce])} availability={view} onAdd={vi.fn()} onClose={vi.fn()} />);

    expect(screen.getByRole('radio', { name: /Pishloqli/ })).toBeDisabled();
    expect(screen.getByRole('radio', { name: /Pishloqli/ })).toHaveTextContent('Tugadi');
    expect(screen.getByRole('radio', { name: /Ketchup/ })).toBeEnabled();
  });

  it('cannot be ordered when every choice of a required group is stopped', () => {
    const view = availabilityView([{ branch_id: 1, stopped: { products: [], variants: [], modifiers: [1, 2] }, sold_out: { variants: [] } }]);
    render(<ProductSheet isOpen product={burger([sauce])} availability={view} onAdd={vi.fn()} onClose={vi.fn()} />);

    expect(confirmButton()).toBeDisabled();
    expect(confirmButton()).toHaveTextContent('"Sous" hozir mavjud emas');
  });

  it('takes a modifier twice when it allows it, with a stepper', () => {
    const doubleCheese: ModifierGroup = { ...extras, modifiers: [modifier(3, 'Pishloq', '2000.00', { modifier_group_id: 2, max_quantity: 2 })] };
    const { onAdd } = renderModal(burger([doubleCheese]));

    fireEvent.click(screen.getByRole('checkbox', { name: /Pishloq/ }));
    fireEvent.click(screen.getByRole('button', { name: 'Pishloq: ko\'paytirish' }));
    expect(confirmButton()).toHaveTextContent(`Tasdiqlash · ${som('54 000')}`);

    fireEvent.click(confirmButton());
    expect(onAdd).toHaveBeenCalledWith(expect.objectContaining({ modifiers: [expect.objectContaining({ id: 3, quantity: 2 })] }));
  });
});

describe('an item sold by weight', () => {
  const meat = (extra: Partial<ProductVariant> = {}) =>
    makeProduct({
      name: 'Qazi',
      variants: [makeVariant({ id: 9, uuid: 'v-meat', name: 'kg', price: '120000.00', unit: 'kg', quantity_step: '0.500', min_quantity: '0.500', stock: 4, ...extra })],
    });

  it('shows the price per kg and starts at the least quantity', () => {
    renderModal(meat());

    expect(screen.getByText(`Narxi: ${som('120 000')} / kg`)).toBeInTheDocument();
    expect(screen.getByText('0,5 kg')).toBeInTheDocument();
    expect(confirmButton()).toHaveTextContent(`Tasdiqlash · ${som('60 000')}`);
  });

  it('moves in the step of the item, never below the minimum', () => {
    const { onAdd } = renderModal(meat());

    fireEvent.click(screen.getByRole('button', { name: "Qazi: ko'paytirish" }));
    fireEvent.click(screen.getByRole('button', { name: "Qazi: ko'paytirish" }));
    expect(screen.getByText('1,5 kg')).toBeInTheDocument();
    expect(confirmButton()).toHaveTextContent(`Tasdiqlash · ${som('180 000')}`);

    fireEvent.click(screen.getByRole('button', { name: 'Qazi: kamaytirish' }));
    fireEvent.click(screen.getByRole('button', { name: 'Qazi: kamaytirish' }));
    fireEvent.click(screen.getByRole('button', { name: 'Qazi: kamaytirish' })); // already at 0,5
    expect(screen.getByText('0,5 kg')).toBeInTheDocument();

    fireEvent.click(confirmButton());
    expect(onAdd).toHaveBeenCalledWith(expect.objectContaining({ quantity: 0.5, modifiers: [] }));
  });

  it('rounds the line like the server: 33 333 per kg × 0.5 kg is 16 667', () => {
    renderModal(meat({ price: '33333.00' }));

    expect(confirmButton()).toHaveTextContent(`Tasdiqlash · ${som('16 667')}`);
  });

  it('does not go past the stock', () => {
    renderModal(meat({ stock: 1 }));

    fireEvent.click(screen.getByRole('button', { name: "Qazi: ko'paytirish" })); // 1 kg is all there is
    fireEvent.click(screen.getByRole('button', { name: "Qazi: ko'paytirish" }));

    expect(screen.getByText('1 kg')).toBeInTheDocument();
  });

  it('counts what the cart already holds of it against the stock', () => {
    useCartStore.setState({
      items: [{ id: '1-v-meat', productId: 1, variantId: 'v-meat', storeId: '1', name: 'Qazi (kg)', price: 120000, quantity: 0.5, unit: 'kg' }],
    });
    renderModal(meat({ stock: 1 }));

    // 0,5 kg is in the cart and 0,5 kg is chosen: that is the whole kilogram there is
    fireEvent.click(screen.getByRole('button', { name: "Qazi: ko'paytirish" }));

    expect(screen.getByText('0,5 kg')).toBeInTheDocument();
  });
});
