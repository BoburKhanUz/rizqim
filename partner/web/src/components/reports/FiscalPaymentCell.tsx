'use client';

import Link from 'next/link';
import { isOnlineFiscalProblem } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { labelFor } from './format';
import { Pill, type Tone } from './ui';

const TONE: Record<string, Tone> = { blocked: 'danger', failed: 'danger', pending: 'warning', recorded: 'success' };

// The fiscal receipt of an online payment (docs/API.md P3-14): its status and, for a payment the owner has to fix, what is
// wrong in plain Uzbek (the problem codes of the server are explained, an unknown one is shown as sent). `null`: the store is
// not in fiscal mode or the payment was cash.
export function FiscalPaymentCell({ fiscal }: { fiscal: { status: string; problems: string[] } | null | undefined }) {
  const { t } = useI18n();
  if (!fiscal) return <span className="text-fg-muted">{t('fiscal_pay_none')}</span>;
  return (
    <div className="flex flex-col items-start gap-1.5">
      <Pill tone={TONE[fiscal.status] ?? 'neutral'}>{labelFor(t, 'fiscal_pay_st_', fiscal.status)}</Pill>
      {fiscal.problems.length > 0 && (
        <ul className="list-disc pl-4 text-footnote text-fg">
          {fiscal.problems.map((code) => (
            <li key={code}>{isOnlineFiscalProblem(code) ? t(`fiscal_problem_${code}`) : t('fiscal_problem_unknown', { code })}</li>
          ))}
        </ul>
      )}
      {fiscal.status === 'blocked' && (
        <>
          <p className="text-footnote text-fg-muted">{t('fiscal_pay_blockedHint')}</p>
          <Link href="/fiscal" className="inline-flex min-h-11 items-center text-footnote font-bold text-brand underline">
            {t('fiscal_pay_fix')}
          </Link>
        </>
      )}
    </div>
  );
}
