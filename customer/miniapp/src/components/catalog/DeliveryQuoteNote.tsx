import { AlertTriangle, Bike, Clock } from 'lucide-react';
import { formatMoney } from '../../lib/format';
import { toAmount } from '../../lib/money';
import type { DeliveryQuoteState } from '../../hooks/useDeliveryQuote';
import { Button, Spinner } from '../ui';

interface DeliveryQuoteNoteProps {
  quote: DeliveryQuoteState & { retry: () => void };
  // Pickup is the way out of an address nobody delivers to; absent when the store takes no pickup
  onChoosePickup?: () => void;
}

// What the server said about delivering to the chosen address, under the address: the fee (or "Bepul"), the minimum
// order and the time, or why it will not deliver there. Nothing is calculated here.
export function DeliveryQuoteNote({ quote, onChoosePickup }: DeliveryQuoteNoteProps) {
  switch (quote.status) {
    case 'off':
    case 'unconfigured':
      return null;

    case 'loading':
      return (
        <p role="status" className="flex items-center gap-2 text-footnote text-fg-muted">
          <Spinner className="size-4" /> Yetkazib berish narxi hisoblanmoqda…
        </p>
      );

    case 'refused':
      return (
        <div role="alert" className="flex items-start gap-3 rounded-card bg-danger-soft p-3 text-danger-soft-fg">
          <AlertTriangle aria-hidden="true" className="mt-0.5 size-5 shrink-0" />
          <div className="flex min-w-0 flex-col items-start gap-2">
            <p className="text-footnote font-bold">Bu manzilga yetkazib berilmaydi</p>
            <p className="text-footnote">{quote.message}</p>
            {onChoosePickup && (
              <Button variant="secondary" onClick={onChoosePickup}>
                Olib ketishni tanlash
              </Button>
            )}
          </div>
        </div>
      );

    case 'error':
      return (
        <div role="alert" className="flex items-start gap-3 rounded-card bg-warning-soft p-3 text-warning-soft-fg">
          <AlertTriangle aria-hidden="true" className="mt-0.5 size-5 shrink-0" />
          <div className="flex min-w-0 flex-col items-start gap-2">
            <p className="text-footnote">Yetkazib berish narxini hisoblab bo'lmadi. Internetni tekshirib, qayta urinib ko'ring.</p>
            <Button variant="secondary" onClick={quote.retry}>
              Qayta urinish
            </Button>
          </div>
        </div>
      );

    case 'ready': {
      const { quote: data } = quote;
      const fee = toAmount(data.delivery_price);
      const minOrder = toAmount(data.min_order_amount);
      return (
        <div role="status" className="flex flex-col gap-1.5 rounded-card bg-surface-2 p-3 text-footnote text-fg-muted">
          <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-fg">
            <span className="flex items-center gap-2 font-bold">
              <Bike aria-hidden="true" className="size-4 shrink-0 text-brand" />
              Yetkazib berish: {fee > 0 ? formatMoney(fee) : 'Bepul'}
            </span>
            {data.eta && (
              <span className="flex items-center gap-1 whitespace-nowrap font-normal text-fg-muted">
                <Clock aria-hidden="true" className="size-4" /> ~{data.eta.minutes} daqiqa
              </span>
            )}
          </p>
          {minOrder > 0 && <p>Minimal buyurtma: {formatMoney(minOrder)}</p>}
        </div>
      );
    }
  }
}
