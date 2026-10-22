import { BookUser, Lock, Phone } from 'lucide-react';
import { useDebtors, type CustomerAccount, type Localized } from '@rizqim/staff';
import { useStaffText } from '../hooks/useStaffText';
import { Card, CardSkeletons, EmptyState, InlineError, PageHeader } from '../components/ui';
import { Pill } from '../components/staff/Pill';
import { getApiErrorMessage } from '../lib/apiError';
import { formatMoney } from '../lib/format';
import { POLL_INTERVAL_MS, pollingQueryOptions } from '../lib/polling';

const TEXT = {
  title: { uz: 'Qarzdorlar', ru: 'Должники' },
  subtitle: { uz: "Nasiya hisoblari: kim qancha qarz", ru: 'Счета насия: кто сколько должен' },
  total: { uz: 'Jami qarz', ru: 'Общий долг' },
  count: { uz: 'Qarzdorlar: {{n}}', ru: 'Должников: {{n}}' },
  empty: { uz: "Qarzdor yo'q", ru: 'Должников нет' },
  emptyText: { uz: "Hozir hech kimning nasiya qarzi yo'q.", ru: 'Сейчас ни у кого нет долга по насия.' },
  limit: { uz: 'Limit: {{amount}}', ru: 'Лимит: {{amount}}' },
  noLimit: { uz: 'Limit: cheksiz', ru: 'Лимит: без ограничения' },
  noCredit: { uz: "Nasiya yo'q", ru: 'Насия нет' },
  blocked: { uz: 'Bloklangan', ru: 'Заблокирован' },
  call: { uz: "{{name}}: qo'ng'iroq qilish", ru: '{{name}}: позвонить' },
  forbidden: { uz: "Nasiya hisoblarini ko'rishga ruxsatingiz yo'q.", ru: 'У вас нет доступа к счетам насия.' },
} satisfies Record<string, Localized>;

function limitLine(account: CustomerAccount, tx: ReturnType<typeof useStaffText<typeof TEXT>>): string {
  if (account.credit_limit === null) return tx('noLimit');
  return Number(account.credit_limit) === 0 ? tx('noCredit') : tx('limit', { amount: formatMoney(account.credit_limit) });
}

// "Qarzdorlar" for an owner or manager on the phone: who owes, the biggest debt first, and the total (GET
// /pos/customer-accounts/debtors). A number to call is one tap away.
export default function Debtors() {
  const tx = useStaffText(TEXT);
  const debtors = useDebtors(pollingQueryOptions(POLL_INTERVAL_MS.debtors));
  const forbidden = debtors.isError && (debtors.error as { status?: number } | null)?.status === 403;

  return (
    <div className="flex flex-col gap-4 px-4 pt-2 animate-fade-in">
      <PageHeader title={tx('title')} subtitle={tx('subtitle')} back />
      {forbidden ? (
        <InlineError title={getApiErrorMessage(debtors.error) || tx('forbidden')} />
      ) : debtors.isError && !debtors.data ? (
        <InlineError onRetry={() => void debtors.refetch()} retrying={debtors.isFetching} />
      ) : !debtors.data ? (
        <CardSkeletons count={3} lines={2} />
      ) : (
        <>
          <Card className="flex flex-col gap-1">
            <p className="text-footnote font-bold text-fg-muted">{tx('total')}</p>
            <p className="text-display font-extrabold text-brand">{formatMoney(debtors.data.total_debt)}</p>
            <p className="text-footnote text-fg-muted">{tx('count', { n: debtors.data.count })}</p>
          </Card>
          {debtors.data.accounts.length === 0 ? (
            <EmptyState icon={BookUser} title={tx('empty')} text={tx('emptyText')} />
          ) : (
            <ul className="flex flex-col gap-3">
              {debtors.data.accounts.map((account) => (
                <li key={account.uuid}>
                  <Card className="flex items-start justify-between gap-3">
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-body font-bold text-fg">{account.name}</p>
                      {account.group_name && <p className="truncate text-footnote text-fg-muted">{account.group_name}</p>}
                      <p className="text-footnote text-fg-muted">{limitLine(account, tx)}</p>
                      {account.is_blocked && (
                        <Pill tone="danger" icon={Lock} className="mt-1">
                          {tx('blocked')}
                        </Pill>
                      )}
                    </div>
                    <div className="flex flex-col items-end gap-1">
                      <p className="text-headline font-extrabold text-fg">{formatMoney(account.balance)}</p>
                      {account.phone && (
                        <a
                          href={`tel:${account.phone}`}
                          aria-label={tx('call', { name: account.name })}
                          className="inline-flex min-h-11 items-center gap-1 rounded-control px-2 text-footnote font-bold text-brand focus-visible:outline-2 focus-visible:outline-brand"
                        >
                          <Phone aria-hidden="true" className="size-4" />
                          {account.phone}
                        </a>
                      )}
                    </div>
                  </Card>
                </li>
              ))}
            </ul>
          )}
        </>
      )}
    </div>
  );
}
