import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { makeProduct, makeVariant } from '../test/factories';
import { availabilityView } from '../lib/menuAvailability';
import { buildCartItem } from '../lib/catalog';
import { cartLineTotal } from '../store/cartStore';
import { comboProblem, defaultComboSelection, toOrderCombo, toggleOption } from '../lib/combo';
import type { ComboOption, ComboSlot, Product, ProductCombo } from '../types';
import type { ProductChoice } from './ProductSheet';
import ProductSheet from './ProductSheet';

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));

// P6-31: a combo on the product sheet — the slots with their options and deltas, the defaults preselected, an option
// the branch cannot sell not offered, the running price, and the cart line and order body it makes.

const option = (over: Partial<ComboOption>): ComboOption => ({
  id: 1,
  uuid: 'o-1',
  variant_id: 21,
  variant_uuid: 'v-21',
  product_id: 2,
  name: 'Lavash',
  variant_name: 'Katta',
  quantity: 1,
  price_delta: '0.00',
  modifiers: [],
  is_default: false,
  label: null,
  receipt_label: 'Asosiy — Lavash (Katta)',
  ...over,
});

const slot = (over: Partial<ComboSlot>): ComboSlot => ({ id: 1, uuid: 's-1', name: 'Asosiy', min_picks: 1, max_picks: 1, is_required: true, allow_modifiers: false, options: [], ...over });

const combo: ProductCombo = {
  id: 1,
  uuid: 'c-1',
  product_id: 9,
  slots: [
    slot({ options: [option({ is_default: true }), option({ id: 2, uuid: 'o-2', variant_id: 22, product_id: 3, name: 'Tovuq lavash', variant_name: null, price_delta: '5000.00', receipt_label: 'Asosiy — Tovuq lavash' })] }),
    slot({
      id: 2,
      uuid: 's-2',
      name: 'Ichimlik',
      options: [
        option({ id: 3, uuid: 'o-3', variant_id: 31, product_id: 4, name: 'Pepsi', variant_name: '0,4 l', is_default: true, receipt_label: 'Ichimlik — 0,4 l Pepsi' }),
        option({ id: 4, uuid: 'o-4', variant_id: 32, product_id: 5, name: 'Ayran', variant_name: null, price_delta: '2000.00', receipt_label: 'Ichimlik — Ayran' }),
      ],
    }),
    slot({ id: 3, uuid: 's-3', name: 'Sous', min_picks: 0, max_picks: 2, is_required: false, options: [option({ id: 5, uuid: 'o-5', variant_id: 41, product_id: 6, name: 'Achchiq sous', variant_name: null, receipt_label: 'Sous — Achchiq sous' })] }),
  ],
};

const comboVariant = makeVariant({ id: 90, uuid: 'v-90', name: 'Kombo', price: '45000.00', stock: 50 });
const lavashKombo = (): Product => makeProduct({ id: 9, name: 'Lavash kombo', variants: [comboVariant], combo });

describe('combo on the product sheet', () => {
  it('preselects the defaults, adds the deltas and sends the parts with the line', () => {
    const onAdd = vi.fn<(choice: ProductChoice) => void>();
    render(<ProductSheet isOpen product={lavashKombo()} onAdd={onAdd} onClose={vi.fn()} />);

    const main = screen.getByRole('radiogroup', { name: 'Asosiy' });
    expect(within(main).getByRole('radio', { name: /Lavash/ })).toHaveAttribute('aria-checked', 'true');
    expect(screen.getByRole('button', { name: /Tasdiqlash/ })).toHaveTextContent("Tasdiqlash · 45 000 so'm");

    fireEvent.click(within(main).getByRole('radio', { name: /Tovuq lavash/ }));
    fireEvent.click(within(screen.getByRole('radiogroup', { name: 'Ichimlik' })).getByRole('radio', { name: /Ayran/ }));
    fireEvent.click(within(screen.getByRole('group', { name: 'Sous' })).getByRole('checkbox', { name: /Achchiq sous/ }));
    expect(screen.getByRole('button', { name: /Tasdiqlash/ })).toHaveTextContent("Tasdiqlash · 52 000 so'm");

    fireEvent.click(screen.getByRole('button', { name: /Tasdiqlash/ }));
    const choice = onAdd.mock.calls[0]![0];
    expect(choice.combo!.map((part) => part.label)).toEqual(['Asosiy — Tovuq lavash', 'Ichimlik — Ayran', 'Sous — Achchiq sous']);

    // The cart line: its own id per pick, the price with the deltas, the order body with the picks only
    const item = buildCartItem(lavashKombo(), comboVariant, { quantity: 2, modifiers: [], combo: choice.combo });
    expect(item.id).toContain('~');
    expect(cartLineTotal(item)).toBe(104000);
    expect(toOrderCombo(item.combo!)).toEqual([{ option_id: 'o-2' }, { option_id: 'o-4' }, { option_id: 'o-5' }]);
  });

  it('does not offer an option the branch cannot sell, and says so when a required slot has none', () => {
    const pepsiGone = availabilityView([{ branch_id: 1, stopped: { products: [4], variants: [], modifiers: [] }, sold_out: { variants: [] } }], 1);
    const { unmount } = render(<ProductSheet isOpen product={lavashKombo()} availability={pepsiGone} onAdd={vi.fn()} onClose={vi.fn()} />);
    const drinks = screen.getByRole('radiogroup', { name: 'Ichimlik' });
    expect(within(drinks).queryByRole('radio', { name: /Pepsi/ })).not.toBeInTheDocument();
    // The default is gone: the drink must be picked
    expect(screen.getByRole('button', { name: /Ichimlik/ })).toBeDisabled();
    fireEvent.click(within(drinks).getByRole('radio', { name: /Ayran/ }));
    expect(screen.getByRole('button', { name: /Tasdiqlash/ })).toBeEnabled();
    unmount();

    const drinksGone = availabilityView([{ branch_id: 1, stopped: { products: [4, 5], variants: [], modifiers: [] }, sold_out: { variants: [] } }], 1);
    expect(comboProblem(combo, defaultComboSelection(combo, drinksGone), drinksGone)).toBe('"Ichimlik" hozir mavjud emas');
  });

  it('keeps a slot of several within its maximum', () => {
    const sauces = slot({ id: 3, name: 'Sous', min_picks: 0, max_picks: 2, options: [option({ uuid: 'a' }), option({ uuid: 'b' }), option({ uuid: 'c' })] });
    let selection = toggleOption({}, sauces, 'a');
    selection = toggleOption(selection, sauces, 'b');
    selection = toggleOption(selection, sauces, 'c');
    expect(selection[3]).toEqual(['a', 'b']);
    expect(toggleOption(selection, sauces, 'a')[3]).toEqual(['b']);
  });

  it('adds the default combo in one tap on the sheet, and says the choice is already made', () => {
    const onAdd = vi.fn<(choice: ProductChoice) => void>();
    render(<ProductSheet isOpen product={lavashKombo()} onAdd={onAdd} onClose={vi.fn()} />);

    expect(screen.getByText("Odatiy tanlov belgilangan: xohlasangiz o'zgartiring")).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /Tasdiqlash/ }));
    expect(onAdd.mock.calls[0]![0].combo!.map((part) => part.label)).toEqual(['Asosiy — Lavash (Katta)', 'Ichimlik — 0,4 l Pepsi']);
  });

  it('says what each choice adds, and that the included one is in the price', () => {
    render(<ProductSheet isOpen product={lavashKombo()} onAdd={vi.fn()} onClose={vi.fn()} />);

    const main = screen.getByRole('radiogroup', { name: 'Asosiy' });
    expect(within(main).getByRole('radio', { name: /^Lavash/ })).toHaveTextContent('Narxda');
    expect(within(main).getByRole('radio', { name: /Tovuq lavash/ })).toHaveTextContent("+5 000 so'm");
    // The sauces cost nothing: no "in the price" on every row
    const sauces = screen.getByRole('group', { name: 'Sous' });
    expect(within(sauces).getByRole('checkbox', { name: /Achchiq sous/ })).not.toHaveTextContent('Narxda');
  });

  it('keeps the confirm button pinned in the sheet while Telegram has no main button', () => {
    render(<ProductSheet isOpen product={lavashKombo()} onAdd={vi.fn()} onClose={vi.fn()} />);
    expect(screen.getByRole('button', { name: /Tasdiqlash/ }).parentElement).toHaveClass('sticky', 'bottom-0');
  });
});
