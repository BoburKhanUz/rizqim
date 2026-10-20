import { useId } from 'react';
import { Check, Minus, Plus } from 'lucide-react';
import { hapticFeedback } from '../../lib/telegram';
import { selectionChanged } from '../../lib/twa';
import { formatMoney } from '../../lib/format';
import { toAmount } from '../../lib/money';
import { groupRule, isSingleChoice, sortedModifiers, stepModifier, toggleModifier, type ModifierSelection } from '../../lib/modifiers';
import type { AvailabilityView } from '../../lib/menuAvailability';
import type { Modifier, ModifierGroup } from '../../types';
import { cx } from '../ui';
import { SoldOutBadge } from './SoldOutBadge';

interface ModifierGroupFieldProps {
  group: ModifierGroup;
  selection: ModifierSelection;
  onChange: (next: ModifierSelection) => void;
  availability: AvailabilityView;
}

const STEP_BUTTON =
  'flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-brand-soft-fg focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand';

// One group of choices of a product ("Sous tanlang"): radio buttons when only one may be chosen, check boxes otherwise,
// with the price each choice adds. A choice the stop-list took off is shown, marked "Tugadi", and cannot be picked.
export function ModifierGroupField({ group, selection, onChange, availability }: ModifierGroupFieldProps) {
  const titleId = useId();
  const single = isSingleChoice(group);

  return (
    <section aria-labelledby={titleId} className="flex min-w-0 flex-col gap-2">
      <div>
        <h3 id={titleId} className="text-body font-bold text-fg">
          {group.name}
        </h3>
        <p className="text-footnote text-fg-muted">{groupRule(group)}</p>
      </div>
      <div role={single ? 'radiogroup' : 'group'} aria-labelledby={titleId} className="flex flex-col gap-2">
        {sortedModifiers(group).map((modifier) => (
          <ModifierRow
            key={modifier.id}
            modifier={modifier}
            single={single}
            count={selection[modifier.id] ?? 0}
            stopped={availability.isModifierStopped(modifier.id)}
            onToggle={() => {
              selectionChanged();
              onChange(toggleModifier(group, selection, modifier));
            }}
            onStep={(delta) => {
              hapticFeedback('light');
              onChange(stepModifier(group, selection, modifier, delta));
            }}
          />
        ))}
      </div>
    </section>
  );
}

interface ModifierRowProps {
  modifier: Modifier;
  single: boolean;
  count: number;
  stopped: boolean;
  onToggle: () => void;
  onStep: (delta: 1 | -1) => void;
}

function ModifierRow({ modifier, single, count, stopped, onToggle, onStep }: ModifierRowProps) {
  const checked = count > 0;
  const delta = toAmount(modifier.price_delta);
  const repeatable = !single && modifier.max_quantity > 1;

  return (
    <div
      className={cx(
        'flex items-center rounded-card border-2 transition-colors',
        stopped ? 'border-line bg-surface-2' : checked ? 'border-brand bg-brand-soft' : 'border-line bg-surface',
      )}
    >
      <button
        type="button"
        role={single ? 'radio' : 'checkbox'}
        aria-checked={checked}
        disabled={stopped}
        onClick={onToggle}
        className="flex min-h-14 min-w-0 flex-1 cursor-pointer items-center gap-3 px-3 py-2 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed"
      >
        <span
          aria-hidden="true"
          className={cx(
            'flex size-6 shrink-0 items-center justify-center border-2',
            single ? 'rounded-full' : 'rounded-control',
            checked ? 'border-brand bg-brand text-on-brand' : 'border-fg-subtle bg-surface',
          )}
        >
          {checked && <Check className="size-4 stroke-[3]" />}
        </span>
        <span className="min-w-0 flex-1">
          <span className={cx('block text-body font-medium', stopped ? 'text-fg-muted' : 'text-fg')}>{modifier.name}</span>
        </span>
        {stopped ? (
          <SoldOutBadge />
        ) : (
          delta !== 0 && <span className="shrink-0 text-body font-bold text-fg">{`${delta > 0 ? '+' : '−'}${formatMoney(Math.abs(delta))}`}</span>
        )}
      </button>
      {repeatable && checked && !stopped && (
        <div role="group" aria-label={`${modifier.name} soni`} className="mr-1 inline-flex shrink-0 items-center rounded-full bg-surface">
          <button type="button" aria-label={`${modifier.name}: kamaytirish`} onClick={() => onStep(-1)} className={STEP_BUTTON}>
            <Minus aria-hidden="true" className="size-[18px]" />
          </button>
          <span aria-live="polite" className="min-w-6 text-center text-body font-bold text-brand-soft-fg">
            {count}
          </span>
          <button
            type="button"
            aria-label={`${modifier.name}: ko'paytirish`}
            aria-disabled={count >= modifier.max_quantity || undefined}
            onClick={() => onStep(1)}
            className={cx(STEP_BUTTON, count >= modifier.max_quantity && 'opacity-40')}
          >
            <Plus aria-hidden="true" className="size-[18px]" />
          </button>
        </div>
      )}
    </div>
  );
}
