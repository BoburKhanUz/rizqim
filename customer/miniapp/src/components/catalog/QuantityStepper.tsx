import { Minus, Plus, Trash2 } from 'lucide-react';
import { cx } from '../ui';

interface QuantityStepperProps {
  quantity: number;
  onDecrement: () => void;
  onIncrement: () => void;
  decrementLabel: string;
  incrementLabel: string;
  // The "-" at quantity 1 removes the line: show a bin instead of a minus
  removeAtOne?: boolean;
  // ... or at this quantity (the least a weighed item can be ordered)
  removeAt?: number;
  // The "+" is at the stock limit: it stays pressable (it says why) but looks off
  incrementDimmed?: boolean;
  // The "-" is at the least that can be ordered (a weighed item's minimum): same
  decrementDimmed?: boolean;
  // What is shown between the buttons instead of the number: "0,5 kg" for an item sold by weight
  valueLabel?: string;
  className?: string;
}

const BUTTON =
  'flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-brand-soft-fg transition-transform motion-safe:active:scale-90 ' +
  'focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand';

// - 2 +: a pill with two 44x44 buttons. Used on the store page and in the cart.
export function QuantityStepper({
  quantity,
  onDecrement,
  onIncrement,
  decrementLabel,
  incrementLabel,
  removeAtOne = false,
  removeAt = 1,
  incrementDimmed = false,
  decrementDimmed = false,
  valueLabel,
  className,
}: QuantityStepperProps) {
  const DecrementIcon = removeAtOne && quantity <= removeAt ? Trash2 : Minus;
  return (
    <div role="group" className={cx('inline-flex shrink-0 items-center rounded-full bg-brand-soft', className)}>
      <button
        type="button"
        aria-label={decrementLabel}
        title={decrementLabel}
        aria-disabled={decrementDimmed || undefined}
        onClick={onDecrement}
        className={cx(BUTTON, decrementDimmed && 'opacity-40')}
      >
        <DecrementIcon aria-hidden="true" className="size-[18px]" />
      </button>
      <span aria-live="polite" className={cx('text-center text-body font-bold text-brand-soft-fg', valueLabel ? 'min-w-14 whitespace-nowrap' : 'min-w-6')}>
        {valueLabel ?? quantity}
      </span>
      <button
        type="button"
        aria-label={incrementLabel}
        title={incrementLabel}
        aria-disabled={incrementDimmed || undefined}
        onClick={onIncrement}
        className={cx(BUTTON, incrementDimmed && 'opacity-40')}
      >
        <Plus aria-hidden="true" className="size-[18px]" />
      </button>
    </div>
  );
}
