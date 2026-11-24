import { Link } from 'react-router-dom';
import { Store as StoreIcon } from 'lucide-react';
import { useLanguageStore } from '../store/languageStore';
import { hapticFeedback } from '../lib/telegram';
import { hasSellerDetails, visibleSellerFields } from '../lib/receipts';
import { cx } from './ui';
import type { StoreSeller } from '../types';

// Wave P (receipts): who sells the food (consumer law art. 28-1: the seller's name, address and how to complain, shown
// before the order is placed and on it). Only the filled fields are shown; nothing at all without any. Complaints go to
// the app's complaint section, and to the restaurant's own contact when it gave one.
// `withTitle` false: the "Sotuvchi" heading and the legal name are shown by the caller (a disclosure's summary).
export function SellerInfo({
  seller,
  className,
  showOperator = false,
  withTitle = true,
}: {
  seller: StoreSeller | null | undefined;
  className?: string;
  showOperator?: boolean;
  withTitle?: boolean;
}) {
  const { t } = useLanguageStore();
  if (!hasSellerDetails(seller)) return null;
  const fields = visibleSellerFields(seller);
  const rows: { label: string; value: string }[] = [];
  if (fields.tin) rows.push({ label: t('sellerTin'), value: fields.tin });
  if (fields.legal_address) rows.push({ label: t('sellerAddress'), value: fields.legal_address });
  if (fields.phone) rows.push({ label: t('sellerPhone'), value: fields.phone });

  return (
    <div data-testid="seller-info" className={cx('flex flex-col gap-2 rounded-control bg-surface-2 p-3 text-footnote', className)}>
      {withTitle && (
        <div className="flex items-start gap-2">
          <StoreIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-brand" />
          <div className="min-w-0">
            <span className="block text-caption text-fg-muted">{t('sellerTitle')}</span>
            {fields.legal_name && <span className="select-text break-words font-bold text-fg">{fields.legal_name}</span>}
          </div>
        </div>
      )}
      {rows.length > 0 && (
        <dl className="flex flex-col gap-1">
          {rows.map((row) => (
            <div key={row.label} className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3">
              <dt className="text-fg-muted">{row.label}</dt>
              <dd className="select-text break-words text-right text-fg">{row.value}</dd>
            </div>
          ))}
        </dl>
      )}
      <p className="text-fg-muted">
        <span className="font-bold text-fg">{t('sellerComplaint')}: </span>
        <Link to="/complaints" onClick={() => hapticFeedback('light')} className="font-medium text-brand underline underline-offset-2">
          {t('sellerComplaintText')}
        </Link>
        {fields.complaint_contact && <span className="select-text"> {t('sellerComplaintContact', { contact: fields.complaint_contact })}</span>}
      </p>
      {showOperator && <p className="text-caption text-fg-muted">{t('sellerOperator')}</p>}
    </div>
  );
}
