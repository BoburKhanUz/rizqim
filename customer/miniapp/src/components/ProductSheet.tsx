import { useMemo, useState } from 'react';
import { Check } from 'lucide-react';
import { hapticFeedback } from '../lib/telegram';
import { nativeMainButton } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import { useMainButton } from '../hooks/useMainButton';
import { formatMoney } from '../lib/format';
import { formatUnitPrice } from '../lib/catalog';
import { toAmount } from '../lib/money';
import { lineTotal } from '../lib/linePrice';
import { groupsOf, selectionProblem, toCartModifiers, type ModifierSelection } from '../lib/modifiers';
import { NO_AVAILABILITY, isProductUnavailable, isVariantUnavailable, type AvailabilityView } from '../lib/menuAvailability';
import { addQuantities, formatQuantity, isMeasuredUnit, minQuantityOf, quantityStepOf, subtractQuantities } from '../lib/quantity';
import { getAddBlockedMessage, getVariantStock } from '../lib/stock';
import { quantityOfVariant, useCartStore, type CartModifier } from '../store/cartStore';
import { Button, Dialog, ProductImage, cx } from './ui';
import { ModifierGroupField } from './catalog/ModifierGroupField';
import { QuantityStepper } from './catalog/QuantityStepper';
import { SoldOutBadge } from './catalog/SoldOutBadge';
import type { Product, ProductVariant } from '../types';

// What the customer picked on the sheet.
export interface ProductChoice {
  variant: ProductVariant;
  quantity: number; // a fraction for an item sold by weight
  modifiers: CartModifier[];
}

interface ProductSheetProps {
  isOpen: boolean;
  onClose: () => void;
  product: Product | null;
  // What is stopped on the menu now (stop-list, sold out); without it only the stock the catalog carries counts
  availability?: AvailabilityView;
  onAdd: (choice: ProductChoice) => void;
}

// The sheet of a product: the variant, the modifier groups (required ones must be chosen), the weight of an item sold
// by the kilogram, and the running price of the line.
export default function ProductSheet({ isOpen, onClose, product, availability = NO_AVAILABILITY, onAdd }: ProductSheetProps) {
  if (!isOpen || !product) return null;

  // Keyed by product so every product starts with its own default selection
  return <Sheet key={product.uuid} product={product} availability={availability} onClose={onClose} onAdd={onAdd} />;
}

interface SheetProps {
  product: Product;
  availability: AvailabilityView;
  onClose: () => void;
  onAdd: (choice: ProductChoice) => void;
}

function Sheet({ product, availability, onClose, onAdd }: SheetProps) {
  const variants = useMemo(() => product.variants ?? [], [product]);
  const groups = useMemo(() => groupsOf(product), [product]);
  const cartItems = useCartStore((state) => state.items);

  // A variant the customer can pick: not switched off, not sold out, not stopped
  const isSelectable = (variant: ProductVariant) => !isVariantUnavailable(product, variant, availability);
  const productUnavailable = isProductUnavailable(product, availability);

  // Starts on the first variant that can be ordered; with none, nothing is selected and nothing can be added
  const [selectedUuid, setSelectedUuid] = useState<string | null>(() => variants.find(isSelectable)?.uuid ?? null);
  const selectedVariant = variants.find((v) => v.uuid === selectedUuid) ?? null;
  const [selection, setSelection] = useState<ModifierSelection>({});
  // The quantity per variant: switching variant starts from that variant's own least quantity
  const [quantities, setQuantities] = useState<Record<string, number>>({});

  const measured = isMeasuredUnit(selectedVariant?.unit);
  const step = quantityStepOf(selectedVariant);
  const minimum = minQuantityOf(selectedVariant);
  const quantity = selectedVariant ? (quantities[selectedVariant.uuid] ?? minimum) : 1;
  const showQuantity = selectedVariant !== null && (measured || groups.length > 0);

  const inCart = quantityOfVariant(cartItems, selectedVariant?.uuid);
  const stock = getVariantStock(selectedVariant);
  const atStockLimit = stock !== null && addQuantities(addQuantities(inCart, quantity), step) > stock;

  // What makes the choice incomplete: a required group without a choice, or one whose every choice is stopped
  const selectionIssue = (() => {
    const issue = selectionProblem(groups, selection);
    if (!issue) return null;
    const blocked = groups.find((group) => {
      const free = group.modifiers.filter((modifier) => !availability.isModifierStopped(modifier.id));
      return group.is_required && free.length === 0;
    });
    return blocked ? `"${blocked.name}" hozir mavjud emas` : issue;
  })();

  const modifiers = toCartModifiers(groups, selection);
  const total = selectedVariant
    ? lineTotal({ price: toAmount(selectedVariant.price), quantity, unit: selectedVariant.unit, modifiers })
    : 0;

  const handleSelect = (variant: ProductVariant) => {
    if (!isSelectable(variant)) return;
    hapticFeedback('light');
    setSelectedUuid(variant.uuid);
  };

  const setQuantity = (next: number) => {
    if (selectedVariant) setQuantities((current) => ({ ...current, [selectedVariant.uuid]: next }));
  };

  const handleIncrement = () => {
    if (!selectedVariant) return;
    const blocked = getAddBlockedMessage(selectedVariant, addQuantities(inCart, quantity), step);
    if (blocked) {
      hapticFeedback('error');
      void showAlert(blocked);
      return;
    }
    hapticFeedback('light');
    setQuantity(addQuantities(quantity, step));
  };

  const handleDecrement = () => {
    if (quantity <= minimum) return;
    hapticFeedback('light');
    setQuantity(Math.max(minimum, subtractQuantities(quantity, step)));
  };

  const canConfirm = selectedVariant !== null && isSelectable(selectedVariant) && selectionIssue === null;

  const handleConfirm = () => {
    if (!selectedVariant || !canConfirm) return;
    hapticFeedback('success');
    onAdd({ variant: selectedVariant, quantity, modifiers });
    onClose();
  };

  const confirmText = productUnavailable
    ? 'Tugadi'
    : !selectedVariant
      ? 'Variant tanlang'
      : selectionIssue ?? `Tasdiqlash · ${formatMoney(total)}`;

  // The confirm action is Telegram's MainButton; outside Telegram the button sits in the sheet itself (the page's
  // own dock is behind the scrim)
  const hasNativeButton = nativeMainButton() !== null;
  useMainButton({ text: confirmText, onClick: handleConfirm, enabled: canConfirm, visible: hasNativeButton });

  const basePrice =
    product.price && toAmount(product.price) > 0
      ? toAmount(product.price)
      : variants.length > 0
        ? Math.min(...variants.map((v) => toAmount(v.price)))
        : 0;

  return (
    <Dialog
      open
      variant="sheet"
      onClose={onClose}
      title={product.name}
      description={variants.length > 1 ? 'Variantni tanlang' : undefined}
      footer={!hasNativeButton ? (
        <Button size="lg" disabled={!canConfirm} onClick={handleConfirm}>
          {confirmText}
        </Button>
      ) : undefined}
    >
      <div className="flex gap-3 rounded-card bg-surface-2 p-3">
        <ProductImage src={product.image_url} className="size-16 rounded-control" />
        <div className="min-w-0 flex-1">
          <p className="line-clamp-2 text-footnote text-fg-muted">{product.description || "Mazali taom"}</p>
          <p className="mt-1 text-footnote font-bold text-fg">
            {variants.length === 1 && variants[0] ? `Narxi: ${formatUnitPrice(variants[0])}` : `Asosiy narx: ${formatMoney(basePrice)}`}
          </p>
        </div>
      </div>

      {variants.length > 1 && (
        <div role="radiogroup" aria-label="Variantlar" className="flex flex-col gap-2">
          {variants.map((v) => {
            const isSelected = selectedVariant?.uuid === v.uuid;
            const isSoldOut = isVariantUnavailable(product, v, availability);

            return (
              <button
                key={v.id}
                type="button"
                role="radio"
                aria-checked={isSelected}
                disabled={isSoldOut}
                onClick={() => handleSelect(v)}
                className={cx(
                  'flex min-h-14 w-full items-center gap-3 rounded-card border-2 px-3 py-2 text-left transition-colors',
                  'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                  isSoldOut
                    ? 'cursor-not-allowed border-line bg-surface-2'
                    : isSelected
                      ? 'cursor-pointer border-brand bg-brand-soft'
                      : 'cursor-pointer border-line bg-surface',
                )}
              >
                <span
                  aria-hidden="true"
                  className={cx(
                    'flex size-6 shrink-0 items-center justify-center rounded-full border-2',
                    isSelected ? 'border-brand bg-brand text-on-brand' : 'border-fg-subtle bg-surface',
                  )}
                >
                  {isSelected && <Check className="size-4 stroke-[3]" />}
                </span>
                <span className="min-w-0 flex-1">
                  <span className={cx('block text-body font-bold', isSoldOut ? 'text-fg-muted' : 'text-fg')}>{v.name}</span>
                </span>
                {isSoldOut && <SoldOutBadge />}
                <span className={cx('shrink-0 pl-2 text-body font-bold', isSoldOut ? 'text-fg-muted' : 'text-fg')}>{formatUnitPrice(v)}</span>
              </button>
            );
          })}
        </div>
      )}

      {selectedVariant &&
        groups.map((group) => (
          <ModifierGroupField key={group.id} group={group} selection={selection} onChange={setSelection} availability={availability} />
        ))}

      {showQuantity && (
        <div className="flex items-center justify-between gap-3 rounded-card bg-surface-2 p-3">
          <div className="min-w-0">
            <p className="text-body font-bold text-fg">{measured ? 'Og\'irligi' : 'Soni'}</p>
            {measured && (
              <p className="text-footnote text-fg-muted">
                {formatQuantity(step, selectedVariant?.unit)} qadam bilan{minimum > step ? `, kamida ${formatQuantity(minimum, selectedVariant?.unit)}` : ''}
              </p>
            )}
          </div>
          <QuantityStepper
            quantity={quantity}
            valueLabel={formatQuantity(quantity, selectedVariant?.unit)}
            decrementLabel={`${product.name}: kamaytirish`}
            incrementLabel={`${product.name}: ko'paytirish`}
            decrementDimmed={quantity <= minimum}
            incrementDimmed={atStockLimit}
            onDecrement={handleDecrement}
            onIncrement={handleIncrement}
          />
        </div>
      )}
      {/* What is still missing, in words at full contrast (a disabled button is dimmed) */}
      {selectionIssue && !productUnavailable && (
        <p role="status" className="text-footnote font-bold text-danger-soft-fg">
          {selectionIssue}
        </p>
      )}
    </Dialog>
  );
}
