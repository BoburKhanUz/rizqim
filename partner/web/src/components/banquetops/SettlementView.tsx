'use client';

import { useState, type FormEvent } from 'react';
import { useSearchParams } from 'next/navigation';
import { Calculator } from 'lucide-react';
import { Button, Card, EmptyState, FormError, InlineError, Notice, PageHeader, Skeleton, formatMoney } from '@rizqim/ui';
import type { MessageKey } from '@/i18n';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { TextField } from '../pos/fields';
import { DataTable, Pill, Td, Th } from '../reports/ui';
import { EVENT_UUID, EventOpsNav, NoEvent, Stat, Warnings, formatQuantity } from './BanquetOpsNav';
import { useSaveGuestsActual, useSettlement } from './hooks';

const newKey = () => (typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`);

// /banquets/settlement?event=<uuid> (P6-54, E5): what the event's final bill is — the billed guests (max of came,
// guaranteed, minimum), the package, hall rent and extras, the event-day orders, the deposits paid and what is left
// to pay at the till or to give back — and the manager's "guests who came". Settling and taking the money are the till's.
export function SettlementView() {
  const { t } = useI18n();
  const uuid = useSearchParams().get('event') ?? '';

  return (
    <>
      <PageHeader title={t('bqo_settlementTitle')} subtitle={t('bqo_settlementSubtitle')} />
      {EVENT_UUID.test(uuid) ? <SettlementPanel uuid={uuid} /> : <NoEvent />}
    </>
  );
}

function SettlementPanel({ uuid }: { uuid: string }) {
  const { t, language } = useI18n();
  const access = useCan();
  const canRead = access.can('banquets.manage') || access.can('pos.take_payment');
  const settlement = useSettlement(uuid);
  const money = (value: string) => formatMoney(value, language);

  if (access.ready && !canRead) return <EmptyState icon={Calculator} title={t('mgmt_forbidden')} text={t('bqo_noAccess')} />;
  if (settlement.isError) return <InlineError title={t('loadErrorTitle')} message={errorText(settlement.error, t)} retryLabel={t('retry')} retrying={settlement.isFetching} onRetry={() => void settlement.refetch()} />;
  const data = settlement.data;
  if (!data) return <Skeleton className="h-64 w-full" />;

  const status: MessageKey = data.settlement === null ? 'bqo_status_none' : data.settlement.status === 'settled' ? 'bqo_status_settled' : 'bqo_status_open';
  const editable = access.can('banquets.manage') && (data.event.status === 'confirmed' || data.event.status === 'in_progress');

  return (
    <>
      <EventOpsNav uuid={uuid} current="settlement" />
      <Warnings warnings={data.warnings} />
      <div className="mb-4 flex flex-wrap items-center gap-2">
        <Pill tone={data.settlement?.status === 'settled' ? 'success' : data.settlement ? 'info' : 'neutral'}>{t(status)}</Pill>
        {data.settlement?.excess_status === 'pending' && <Pill tone="warning">{t('bqo_excess_pending')}</Pill>}
        {data.settlement?.excess_status === 'refunded' && <Pill tone="success">{t('bqo_excess_refunded')}</Pill>}
      </div>

      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label={t('bqo_guestsBilled')} value={data.guests.billed} hint={t('bqo_guestsLine', { guaranteed: data.guests.guaranteed, minimum: data.guests.minimum, actual: data.guests.actual ?? '—' })} />
        <Stat label={t('bqo_total')} value={money(data.total)} />
        <Stat label={t('bqo_advances')} value={money(data.advances.total)} />
        {Number(data.excess) > 0 ? <Stat label={t('bqo_excess')} value={money(data.excess)} /> : <Stat label={t('bqo_due')} value={money(data.due)} />}
      </div>

      {editable && <GuestsForm uuid={uuid} actual={data.guests.actual} />}

      <Card className="mb-4 flex flex-col gap-3">
        <DataTable caption={t('bqo_settlementTitle')}>
          <thead>
            <tr>
              <Th>{t('bqo_colDish')}</Th>
              <Th numeric>{t('bqo_colQuantity')}</Th>
              <Th numeric>{t('bqo_total')}</Th>
            </tr>
          </thead>
          <tbody>
            {data.lines.map((line) => (
              <tr key={line.uuid}>
                <Td>{line.name}</Td>
                <Td numeric muted>
                  {line.persons !== null ? `${formatQuantity(line.quantity, language)} × ${t('bqo_persons', { n: line.persons })}` : formatQuantity(line.quantity, language)} × {money(line.unit_price)}
                </Td>
                <Td numeric className="whitespace-nowrap font-bold">
                  {money(line.amount)}
                </Td>
              </tr>
            ))}
            <tr>
              <Td>
                {t('bqo_eventDay')}
                {Number(data.orders.service_charge) > 0 && <span className="text-fg-muted"> ({t('bqo_serviceCharge', { amount: money(data.orders.service_charge) })})</span>}
              </Td>
              <Td />
              <Td numeric className="whitespace-nowrap font-bold">
                {money(data.orders.total)}
              </Td>
            </tr>
          </tbody>
        </DataTable>
        <p className="text-footnote text-fg-muted">{t('bqo_tillOnly')}</p>
      </Card>
    </>
  );
}

// The manager's "guests who came" (banquets.manage; confirmed or in progress)
function GuestsForm({ uuid, actual }: { uuid: string; actual: number | null }) {
  const { t } = useI18n();
  const [guests, setGuests] = useState(actual === null ? '' : String(actual));
  const [key, setKey] = useState(newKey);
  const [notice, setNotice] = useState<string | null>(null);
  const save = useSaveGuestsActual(uuid, {
    onSuccess: () => {
      setNotice(t('bqo_guestsSaved'));
      setKey(newKey());
    },
  });
  const typed = Number(guests);
  const valid = guests.trim() !== '' && Number.isInteger(typed) && typed >= 0 && typed <= 100000;
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (valid) save.mutate({ guests: typed, key });
  };

  return (
    <Card className="mb-4">
      <form className="flex flex-wrap items-end gap-3" onSubmit={submit}>
        <TextField label={t('bqo_guestsActual')} value={guests} onChange={setGuests} inputMode="numeric" maxLength={6} />
        <Button type="submit" loading={save.isPending} disabled={!valid}>
          {t('bqo_saveGuests')}
        </Button>
      </form>
      <Notice message={notice} />
      {save.isError && <FormError message={errorText(save.error, t)} />}
    </Card>
  );
}
