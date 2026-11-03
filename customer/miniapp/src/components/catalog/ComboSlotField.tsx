import { useId } from 'react';
import { Check } from 'lucide-react';
import { selectionChanged } from '../../lib/twa';
import { formatMoney } from '../../lib/format';
import { toAmount } from '../../lib/money';
import type { ComboOption, ComboSlot } from '../../types';
import { cx } from '../ui';

interface ComboSlotFieldProps {
  slot: ComboSlot;
  // The options the branch can sell now (an unavailable one is not offered)
  options: ComboOption[];
  picked: string[];
  onPick: (optionUuid: string) => void;
}

// P6-31: one slot of a combo ("Ichimlik"): radio buttons when one is picked, check boxes when several may be, with what
// each option adds to the combo's price. The default is preselected; the server checks the pick again at checkout.
export function ComboSlotField({ slot, options, picked, onPick }: ComboSlotFieldProps) {
  const titleId = useId();
  const single = Math.max(1, slot.max_picks) === 1;
  // When some choices cost extra, the free ones say they are in the price ("Narxda") so no one wonders about a missing number
  const hasExtra = options.some((option) => toAmount(option.price_delta) > 0);
  const rule = slot.min_picks > 0 ? (single ? 'Majburiy' : `Majburiy, ${slot.min_picks}–${slot.max_picks} ta`) : single ? 'Ixtiyoriy' : `Ixtiyoriy, ko'pi bilan ${slot.max_picks} ta`;

  return (
    <section aria-labelledby={titleId} className="flex min-w-0 flex-col gap-2">
      <div>
        <h3 id={titleId} className="text-body font-bold text-fg">
          {slot.name}
        </h3>
        <p className="text-footnote text-fg-muted">{options.length === 0 ? 'Hozir mavjud emas' : rule}</p>
      </div>
      <div role={single ? 'radiogroup' : 'group'} aria-labelledby={titleId} className="flex flex-col gap-2">
        {options.map((option) => {
          const checked = picked.includes(option.uuid);
          const delta = toAmount(option.price_delta);
          return (
            <button
              key={option.uuid}
              type="button"
              role={single ? 'radio' : 'checkbox'}
              aria-checked={checked}
              onClick={() => {
                selectionChanged();
                onPick(option.uuid);
              }}
              className={cx(
                'flex min-h-14 w-full cursor-pointer items-center gap-3 rounded-card border-2 px-3 py-2 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                checked ? 'border-brand bg-brand-soft' : 'border-line bg-surface',
              )}
            >
              <span aria-hidden="true" className={cx('flex size-6 shrink-0 items-center justify-center border-2', single ? 'rounded-full' : 'rounded-control', checked ? 'border-brand bg-brand text-on-brand' : 'border-fg-subtle bg-surface')}>
                {checked && <Check className="size-4 stroke-[3]" />}
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-body font-medium text-fg">
                  {option.quantity > 1 ? `${option.quantity} × ` : ''}
                  {option.name}
                </span>
                {(option.variant_name || option.modifiers.length > 0) && (
                  <span className="block text-footnote text-fg-muted">{[option.variant_name, ...option.modifiers.map((modifier) => modifier.name)].filter(Boolean).join(', ')}</span>
                )}
              </span>
              {delta > 0 ? (
                <span className="shrink-0 text-body font-bold text-fg">{`+${formatMoney(delta)}`}</span>
              ) : (
                hasExtra && <span className="shrink-0 text-footnote text-fg-muted">Narxda</span>
              )}
            </button>
          );
        })}
      </div>
    </section>
  );
}
