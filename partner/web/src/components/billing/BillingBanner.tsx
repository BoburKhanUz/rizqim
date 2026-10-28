'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Lock, TriangleAlert } from 'lucide-react';
import { cx, formatMoney } from '@rizqim/ui';
import { daysUntilLock, useEntitlements } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';

// The unpaid-invoice notice across the panel (P4-21, docs/BILLING.md "Eslatmalar va cheklov"): an invoice more than a day past its
// due date shows "To'lov muddati o'tdi — X kundan keyin sozlamalar yopiladi"; a week past, "Sozlamalar yopiq — to'lov qiling".
// The server computes it live (`notice`), so paying makes it disappear on the next read. The till is never part of the message
// other than to say it keeps working.
export function BillingBanner({ canPay }: { canPay: boolean }) {
  const { t, language } = useI18n();
  const pathname = usePathname();
  const query = useEntitlements();
  const entitlements = query.data;
  const notice = entitlements?.notice;
  if (!entitlements || !notice) return null;

  const locked = notice.level === 'locked';
  const days = daysUntilLock(entitlements);
  const title = locked
    ? t('billing_bannerLocked')
    : days === null
      ? t('billing_bannerReminderNoDate')
      : days <= 0
        ? t('billing_bannerReminderToday')
        : t('billing_bannerReminder', { days });
  const amount = formatMoney(notice.invoice.outstanding, language);
  const text = t(locked ? 'billing_bannerLockedText' : 'billing_bannerReminderText', { number: notice.invoice.number, amount });
  const Icon = locked ? Lock : TriangleAlert;

  return (
    <section
      role={locked ? 'alert' : 'status'}
      aria-label={t('billing_bannerAria')}
      className={cx(
        'mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-card px-4 py-3 print:hidden',
        locked ? 'bg-danger-soft text-danger-soft-fg' : 'bg-warning-soft text-warning-soft-fg',
      )}
    >
      <Icon aria-hidden="true" className="size-5 shrink-0" />
      <div className="flex min-w-0 flex-1 basis-64 flex-col">
        <p className="text-body font-bold">{title}</p>
        <p className="text-footnote">{text}</p>
      </div>
      {canPay && pathname !== '/billing' && (
        <Link href="/billing" className="inline-flex min-h-11 items-center rounded-control border border-current px-4 text-body font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
          {t('billing_bannerLink')}
        </Link>
      )}
    </section>
  );
}
