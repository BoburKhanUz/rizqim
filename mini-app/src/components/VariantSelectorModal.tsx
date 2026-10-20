import { useState } from 'react';
import { Check } from 'lucide-react';
import { hapticFeedback } from '../lib/telegram';
import { nativeMainButton } from '../lib/twa';
import { useMainButton } from '../hooks/useMainButton';
import { formatMoney } from '../lib/format';
import { toAmount } from '../lib/money';
import { isVariantActive, isVariantOutOfStock } from '../lib/stock';
import { Button, Dialog, cx } from './ui';
import { ProductImage } from './catalog/ProductImage';
import type { Product, ProductVariant } from '../types';

interface VariantSelectorModalProps {
  isOpen: boolean;
  onClose: () => void;
  product: Product | null;
  onAdd: (variant: ProductVariant) => void;
}

// A variant the customer can pick: switched on by the store and not known to be sold out.
const isSelectable = (variant: ProductVariant) => isVariantActive(variant) && !isVariantOutOfStock(variant);

export default function VariantSelectorModal({ isOpen, onClose, product, onAdd }: VariantSelectorModalProps) {
  if (!isOpen || !product) return null;

  // Keyed by product so every product starts with its own default selection
  return <VariantSheet key={product.uuid} product={product} onClose={onClose} onAdd={onAdd} />;
}

interface VariantSheetProps {
  product: Product;
  onClose: () => void;
  onAdd: (variant: ProductVariant) => void;
}

function VariantSheet({ product, onClose, onAdd }: VariantSheetProps) {
  const variants = product.variants ?? [];
  // Starts on the first variant that can be ordered; with none, nothing is selected and nothing can be added
  const [selectedUuid, setSelectedUuid] = useState<string | null>(() => variants.find(isSelectable)?.uuid ?? null);
  const selectedVariant = variants.find((v) => v.uuid === selectedUuid) ?? null;

  const handleSelect = (variant: ProductVariant) => {
    if (!isSelectable(variant)) return;
    hapticFeedback('light');
    setSelectedUuid(variant.uuid);
  };

  const handleConfirm = () => {
    if (!selectedVariant || !isSelectable(selectedVariant)) return;
    hapticFeedback('success');
    onAdd(selectedVariant);
    onClose();
  };

  const confirmText = selectedVariant ? `Tasdiqlash — ${formatMoney(selectedVariant.price)}` : 'Variant tanlang';

  // The confirm action is Telegram's MainButton; outside Telegram the button sits in the sheet itself (the page's
  // own dock is behind the scrim)
  const hasNativeButton = nativeMainButton() !== null;
  useMainButton({ text: confirmText, onClick: handleConfirm, enabled: !!selectedVariant, visible: hasNativeButton });

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
      description="Variantni tanlang"
      footer={!hasNativeButton ? (
        <Button size="lg" disabled={!selectedVariant} onClick={handleConfirm}>
          {confirmText}
        </Button>
      ) : undefined}
    >
      <div className="flex gap-3 rounded-card bg-surface-2 p-3">
        <ProductImage src={product.image_url} className="size-16 rounded-control" />
        <div className="min-w-0 flex-1">
          <p className="line-clamp-2 text-footnote text-fg-muted">{product.description || "Mazali taom variantini tanlang"}</p>
          <p className="mt-1 text-footnote font-bold text-fg">Asosiy narx: {formatMoney(basePrice)}</p>
        </div>
      </div>

      <div role="radiogroup" aria-label="Variantlar" className="flex flex-col gap-2">
        {variants.map((v) => {
          const isSelected = selectedVariant?.uuid === v.uuid;
          const isOutOfStock = isVariantOutOfStock(v);
          const isDisabled = !isSelectable(v);

          return (
            <button
              key={v.id}
              type="button"
              role="radio"
              aria-checked={isSelected}
              disabled={isDisabled}
              onClick={() => handleSelect(v)}
              className={cx(
                'flex min-h-14 w-full items-center gap-3 rounded-card border-2 px-3 py-2 text-left transition-colors',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                isDisabled
                  ? 'cursor-not-allowed border-line bg-surface-2 opacity-60'
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
                <span className="block text-body font-bold text-fg">{v.name}</span>
                {isOutOfStock && <span className="block text-caption font-bold text-danger-soft-fg">Tugagan</span>}
              </span>
              <span className="shrink-0 pl-2 text-body font-bold text-fg">{formatMoney(v.price)}</span>
            </button>
          );
        })}
      </div>
    </Dialog>
  );
}
