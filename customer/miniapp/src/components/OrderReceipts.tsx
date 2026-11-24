import { Clock, ReceiptText, TriangleAlert } from 'lucide-react';
import { useLanguageStore } from '../store/languageStore';
import { hapticFeedback } from '../lib/telegram';
import { openExternalLink } from '../lib/openMap';
import { isOpenableReceipt, isTaxReceiptUrl, receiptLabelKey } from '../lib/receipts';
import { cx } from './ui';
import type { OrderReceipt } from '../types';

// Wave P (receipts): the order's fiscal receipts (ZRU-792 art. 23: the receipt reaches the buyer's personal cabinet).
// A ready one is a button that opens its ofd.soliq.uz page through Telegram (openLink); a pending or unavailable one is a
// line saying so ("tayyorlanmoqda", "restorandan so'rang"). Nothing without receipts.
export function OrderReceipts({ receipts }: { receipts: OrderReceipt[] | undefined }) {
  const { t } = useLanguageStore();
  if (!receipts || receipts.length === 0) return null;

  return (
    <div data-testid="order-receipts" className="flex flex-col gap-2">
      {receipts.map((receipt, index) => {
        const label = t(receiptLabelKey(receipt));
        const key = receipt.uuid ?? `${receipt.type}-${index}`;
        if (isOpenableReceipt(receipt)) {
          return (
            <button
              key={key}
              type="button"
              onClick={() => {
                if (!isTaxReceiptUrl(receipt.url)) return;
                hapticFeedback('light');
                openExternalLink(receipt.url);
              }}
              className={cx(
                'inline-flex min-h-11 w-full cursor-pointer items-center justify-center gap-1.5 rounded-card px-4 text-body font-bold',
                'transition-transform motion-safe:active:scale-[0.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                'bg-brand-soft text-brand-soft-fg',
              )}
            >
              <ReceiptText aria-hidden="true" className="size-4" />
              {label}
            </button>
          );
        }
        const Icon = receipt.status === 'pending' ? Clock : TriangleAlert;
        return (
          <p key={key} className="flex items-center gap-2 rounded-control bg-surface-2 p-3 text-footnote text-fg-muted">
            <Icon aria-hidden="true" className="size-4 shrink-0" />
            {label}
          </p>
        );
      })}
    </div>
  );
}
