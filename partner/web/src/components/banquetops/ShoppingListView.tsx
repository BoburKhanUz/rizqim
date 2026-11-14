'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Download, Printer, ShoppingBasket } from 'lucide-react';
import { Button, Card, EmptyState, InlineError, Notice, PageHeader, Skeleton, formatDate } from '@rizqim/ui';
import { todayIn } from '@/lib/storeTime';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { DateField } from '../pos/fields';
import { saveBlob } from '../reports/download';
import { DataTable, Td, Th } from '../reports/ui';
import type { DateRange } from './api';
import { formatQuantity } from './BanquetOpsNav';
import { useDownloadShoppingList, useShoppingList } from './hooks';
import { DishTable, unitKey } from './ProductionSheetView';

function addDays(date: string, days: number): string {
  const day = new Date(`${date}T00:00:00Z`);
  day.setUTCDate(day.getUTCDate() + days);
  return day.toISOString().slice(0, 10);
}

// /banquets/shopping "Bozorlik ro'yxati" (P6-54, E4): the osh ingredients and the dishes of the events on the chosen
// days summed (a week from today by default), each event's planned guests; printed or downloaded as Excel.
export function ShoppingListView() {
  const { t, language } = useI18n();
  const access = useCan();
  const canRead = ['banquets.manage', 'pos.take_payment', 'reports.view', 'kitchen.manage'].some((permission) => access.can(permission));
  const today = todayIn('Asia/Tashkent');
  const [from, setFrom] = useState(today);
  const [to, setTo] = useState(addDays(today, 6));
  const [notice, setNotice] = useState<string | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);
  const reversed = from !== '' && to !== '' && from > to;
  const range: DateRange = { ...(from ? { from } : {}), ...(to ? { to } : {}) };
  const list = useShoppingList(range, canRead && !reversed);
  const download = useDownloadShoppingList({
    onSuccess: ({ blob, filename }) => {
      saveBlob(blob, filename);
      setNotice(t('bqo_exportDone', { name: filename }));
    },
    onError: () => setExportError(t('bqo_exportFailed')),
  });
  const quantity = (value: string) => formatQuantity(value, language);

  if (access.ready && !canRead) {
    return (
      <>
        <PageHeader title={t('bqo_shoppingTitle')} />
        <EmptyState icon={ShoppingBasket} title={t('mgmt_forbidden')} text={t('bqo_noAccess')} />
      </>
    );
  }
  const data = list.data;

  return (
    <>
      <PageHeader
        title={t('bqo_shoppingTitle')}
        subtitle={t('bqo_shoppingSubtitle')}
        action={
          <div className="flex gap-2 print:hidden">
            <Button variant="secondary" icon={Printer} onClick={() => window.print()}>
              {t('bqo_print')}
            </Button>
            <Button
              variant="secondary"
              icon={Download}
              loading={download.isPending}
              disabled={reversed}
              onClick={() => {
                setNotice(null);
                setExportError(null);
                download.mutate(range);
              }}
            >
              {download.isPending ? t('bqo_exporting') : t('bqo_export')}
            </Button>
          </div>
        }
      />
      <Notice message={notice} />
      {exportError && (
        <p role="alert" className="mb-4 text-footnote font-bold text-danger-soft-fg">
          {exportError}
        </p>
      )}
      <Card className="mb-4 flex flex-col gap-3 print:hidden">
        <div className="flex flex-wrap items-end gap-3">
          <DateField label={t('report_from')} value={from} onChange={setFrom} />
          <DateField label={t('report_to')} value={to} onChange={setTo} />
          <Link href="/banquets/ops-settings" className="font-bold text-brand underline-offset-2 hover:underline">
            {t('bqo_navSettings')}
          </Link>
        </div>
        {reversed && (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {t('report_problem_reversed')}
          </p>
        )}
      </Card>

      {list.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(list.error, t)} retryLabel={t('retry')} retrying={list.isFetching} onRetry={() => void list.refetch()} />
      ) : !data ? (
        <Skeleton className="h-48 w-full" />
      ) : data.events.length === 0 ? (
        <EmptyState icon={ShoppingBasket} title={t('bqo_shoppingEmpty')} text={t('bqo_shoppingEmptyText')} />
      ) : (
        <>
          {data.osh.ingredients.length > 0 && (
            <Card className="mb-4 flex flex-col gap-3">
              <h2 className="text-headline font-extrabold text-fg">{t('bqo_oshIngredients')}</h2>
              <p className="text-body text-fg">{t('bqo_oshSummary', { persons: data.osh.persons, kg: quantity(data.osh.osh_kg) })}</p>
              <DataTable caption={t('bqo_oshIngredients')}>
                <thead>
                  <tr>
                    <Th>{t('bqo_colProduct')}</Th>
                    <Th numeric>{t('bqo_colQuantity')}</Th>
                  </tr>
                </thead>
                <tbody>
                  {data.osh.ingredients.map((ingredient) => (
                    <tr key={ingredient.key}>
                      <Td>{ingredient.name}</Td>
                      <Td numeric className="font-bold">
                        {quantity(ingredient.quantity)} {t(unitKey(ingredient.unit))}
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </DataTable>
            </Card>
          )}
          <Card className="mb-4 flex flex-col gap-3">
            <h2 className="text-headline font-extrabold text-fg">{t('bqo_dishes')}</h2>
            <DishTable caption={t('bqo_dishes')} dishes={data.dishes} quantity={quantity} perPerson={false} />
          </Card>
          <Card className="mb-4 flex flex-col gap-3">
            <h2 className="text-headline font-extrabold text-fg">{t('bqo_events')}</h2>
            <DataTable caption={t('bqo_events')}>
              <thead>
                <tr>
                  <Th>{t('bqo_colDate')}</Th>
                  <Th>{t('bqo_colEvent')}</Th>
                  <Th numeric>{t('bqo_colGuests')}</Th>
                </tr>
              </thead>
              <tbody>
                {data.events.map((event) => (
                  <tr key={event.uuid}>
                    <Td muted className="whitespace-nowrap">
                      {formatDate(event.event_date, language)}
                    </Td>
                    <Td>
                      <Link href={`/banquets/production?event=${event.uuid}`} className="font-bold text-brand underline-offset-2 hover:underline">
                        {event.title ?? event.event_type}
                      </Link>
                    </Td>
                    <Td numeric>{event.planned_guests}</Td>
                  </tr>
                ))}
              </tbody>
            </DataTable>
          </Card>
        </>
      )}
    </>
  );
}
