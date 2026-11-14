'use client';

import { useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { ChefHat, Download, Printer } from 'lucide-react';
import { Button, Card, EmptyState, InlineError, Notice, PageHeader, Skeleton, formatDate } from '@rizqim/ui';
import type { MessageKey } from '@/i18n';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { saveBlob } from '../reports/download';
import { DataTable, Td, Th } from '../reports/ui';
import { kazansText, type Dish, type OshResult } from './api';
import { EVENT_UUID, EventOpsNav, NoEvent, Stat, Warnings, formatQuantity } from './BanquetOpsNav';
import { useDownloadProductionSheet, useProductionSheet } from './hooks';

export const unitKey = (unit: string): MessageKey => (['portion', 'piece', 'kg', 'l', 'dona'].includes(unit) ? (`bqo_unit_${unit}` as MessageKey) : 'bqo_unit_piece');

// /banquets/production?event=<uuid> (P6-54, E4): the kitchen's production sheet of one event — the package's dishes for
// the planned guests by station, the osh calculator's kazans and ingredients, the warnings; printed from the browser or
// downloaded as Excel. No customer data (banquets.manage, pos.take_payment, reports.view or kitchen.manage).
export function ProductionSheetView() {
  const { t } = useI18n();
  const uuid = useSearchParams().get('event') ?? '';

  return (
    <>
      <PageHeader title={t('bqo_productionTitle')} subtitle={t('bqo_productionSubtitle')} />
      {EVENT_UUID.test(uuid) ? <ProductionSheet uuid={uuid} /> : <NoEvent />}
    </>
  );
}

function ProductionSheet({ uuid }: { uuid: string }) {
  const { t, language } = useI18n();
  const access = useCan();
  const canRead = ['banquets.manage', 'pos.take_payment', 'reports.view', 'kitchen.manage'].some((permission) => access.can(permission));
  const sheet = useProductionSheet(uuid);
  const [notice, setNotice] = useState<string | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);
  const download = useDownloadProductionSheet({
    onSuccess: ({ blob, filename }) => {
      saveBlob(blob, filename);
      setNotice(t('bqo_exportDone', { name: filename }));
    },
    onError: () => setExportError(t('bqo_exportFailed')),
  });
  const quantity = (value: string) => formatQuantity(value, language);

  if (access.ready && !canRead) return <EmptyState icon={ChefHat} title={t('mgmt_forbidden')} text={t('bqo_noAccess')} />;
  if (sheet.isError) return <InlineError title={t('loadErrorTitle')} message={errorText(sheet.error, t)} retryLabel={t('retry')} retrying={sheet.isFetching} onRetry={() => void sheet.refetch()} />;
  const data = sheet.data;
  if (!data) return <Skeleton className="h-64 w-full" />;
  const dishes = data.stations.flatMap((station) => station.dishes);

  return (
    <>
      <EventOpsNav uuid={uuid} current="production" />
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-col">
          <span className="text-headline font-extrabold text-fg">{data.event.title ?? data.event.event_type_label}</span>
          <span className="text-footnote text-fg-muted">
            {formatDate(data.event.event_date, language)} · {data.event.local_time} · {data.event.halls.join(', ')}
          </span>
        </div>
        <div className="flex gap-2 print:hidden">
          <Button variant="secondary" icon={Printer} onClick={() => window.print()}>
            {t('bqo_print')}
          </Button>
          <Button
            variant="secondary"
            icon={Download}
            loading={download.isPending}
            onClick={() => {
              setNotice(null);
              setExportError(null);
              download.mutate({ uuid, eventDate: data.event.event_date });
            }}
          >
            {download.isPending ? t('bqo_exporting') : t('bqo_export')}
          </Button>
        </div>
      </div>
      <Notice message={notice} />
      {exportError && (
        <p role="alert" className="mb-4 text-footnote font-bold text-danger-soft-fg">
          {exportError}
        </p>
      )}
      <Warnings warnings={data.warnings} />

      <div className="mb-4 grid gap-3 sm:grid-cols-2">
        <Stat label={t('bqo_guestsBase')} value={data.guests.base} hint={t('bqo_guestsLine', { guaranteed: data.guests.guaranteed, minimum: data.guests.minimum, actual: data.guests.actual ?? '—' })} />
        <Stat label={t('bqo_guestsPlanned', { percent: Number(data.guests.reserve_percent) })} value={data.guests.planned} />
      </div>

      {dishes.length === 0 ? (
        <Card className="mb-4">
          <EmptyState icon={ChefHat} title={t('bqo_noDishes')} text={t('bqo_noDishesText')} />
        </Card>
      ) : (
        data.stations.map((station) => (
          <Card key={station.station_id ?? 'none'} className="mb-4 flex flex-col gap-3">
            <h2 className="text-headline font-extrabold text-fg">{station.name}</h2>
            <DishTable caption={station.name} dishes={station.dishes} quantity={quantity} />
          </Card>
        ))
      )}

      {data.osh && <OshCard osh={data.osh} />}
    </>
  );
}

export function DishTable({ caption, dishes, quantity, perPerson = true }: { caption: string; dishes: Dish[]; quantity: (value: string) => string; perPerson?: boolean }) {
  const { t } = useI18n();

  return (
    <DataTable caption={caption}>
      <thead>
        <tr>
          <Th>{t('bqo_colDish')}</Th>
          {perPerson && <Th numeric>{t('bqo_colPerPerson')}</Th>}
          <Th numeric>{t('bqo_colQuantity')}</Th>
          <Th numeric>{t('bqo_colKg')}</Th>
        </tr>
      </thead>
      <tbody>
        {dishes.map((dish) => (
          <tr key={`${dish.product_id ?? dish.name}-${dish.unit}`}>
            <Td>{dish.name}</Td>
            {perPerson && (
              <Td numeric muted>
                {dish.quantity_per_person !== null ? `${quantity(dish.quantity_per_person)} ${t(unitKey(dish.unit))}` : '—'}
              </Td>
            )}
            <Td numeric className="font-bold">
              {quantity(dish.quantity)} {t(unitKey(dish.unit))}
            </Td>
            <Td numeric>{dish.kg !== null ? quantity(dish.kg) : '—'}</Td>
          </tr>
        ))}
      </tbody>
    </DataTable>
  );
}

export function OshCard({ osh }: { osh: OshResult }) {
  const { t, language } = useI18n();

  return (
    <Card className="mb-4 flex flex-col gap-3">
      <h2 className="text-headline font-extrabold text-fg">{t('bqo_osh')}</h2>
      <p className="text-body text-fg">{t('bqo_oshSummary', { persons: osh.persons, kg: formatQuantity(osh.osh_kg, language) })}</p>
      {osh.kazans.length > 0 && (
        <p className="text-body text-fg">
          <span className="font-bold">{t('bqo_kazans')}:</span> {kazansText(osh.kazans)}
        </p>
      )}
      <DataTable caption={t('bqo_oshIngredients')}>
        <thead>
          <tr>
            <Th>{t('bqo_ingredientName')}</Th>
            <Th numeric>{t('bqo_colQuantity')}</Th>
          </tr>
        </thead>
        <tbody>
          {osh.ingredients.map((ingredient) => (
            <tr key={ingredient.key}>
              <Td>{ingredient.name}</Td>
              <Td numeric className="font-bold">
                {formatQuantity(ingredient.quantity, language)} {t(unitKey(ingredient.unit))}
              </Td>
            </tr>
          ))}
        </tbody>
      </DataTable>
    </Card>
  );
}
