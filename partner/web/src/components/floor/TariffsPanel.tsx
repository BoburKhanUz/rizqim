'use client';

import { useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { Button, Card, EmptyState, IconButton, formatDate, formatMoney } from '@rizqim/ui';
import { useDeleteRoomTariff, useHalls, useRoomTariffs, type RoomTariff } from '@rizqim/staff';
import { Banknote } from 'lucide-react';
import { useI18n } from '../I18nProvider';
import { ConfirmDialog } from '../management/ConfirmDialog';
import { errorText } from '../management/errors';
import { Notice } from '../management/Notice';
import { Pill } from '../management/Pill';
import { Gate } from '../pos/Gate';
import { labelFor, localDate } from '../reports/format';
import { DataTable, Td, Th } from '../reports/ui';
import { CalendarCard } from './CalendarCard';
import { TariffDialog } from './TariffDialog';

// Room tariffs of a branch (by room or by kind of place, by weekday / weekend / holiday, for a range of dates) and the
// branch's weekend/holiday calendar they are read with. The server picks the tariff for a day and prices the room.
export function TariffsPanel({ branchId }: { branchId: number }) {
  const { t, language } = useI18n();
  const tariffs = useRoomTariffs(branchId);
  const halls = useHalls(branchId);
  const remove = useDeleteRoomTariff();
  const [dialog, setDialog] = useState<{ tariff: RoomTariff | null } | null>(null);
  const [deleting, setDeleting] = useState<RoomTariff | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const places = (halls.data ?? []).flatMap((hall) => hall.tables.map((table) => ({ id: table.id, name: table.name, kind: table.kind, hallName: hall.name })));
  const hallName = (id: number | null) => (id === null ? null : ((halls.data ?? []).find((hall) => hall.id === id)?.name ?? null));

  const scopeOf = (tariff: RoomTariff) =>
    tariff.dining_table_id !== null
      ? (tariff.dining_table_name ?? `#${tariff.dining_table_id}`)
      : `${labelFor(t, 'floor_kindAll_', tariff.table_kind)}${tariff.hall_id !== null ? ` · ${hallName(tariff.hall_id) ?? `#${tariff.hall_id}`}` : ` · ${t('floor_tariff_allHalls')}`}`;
  const period = (tariff: RoomTariff) =>
    tariff.valid_from || tariff.valid_to
      ? `${tariff.valid_from ? formatDate(localDate(tariff.valid_from), language) : '…'} — ${tariff.valid_to ? formatDate(localDate(tariff.valid_to), language) : '…'}`
      : t('floor_tariff_always');

  return (
    <Gate query={tariffs} count={3}>
      {(data) => (
        <div className="flex flex-col gap-6">
          <Card className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 className="text-headline font-bold text-fg">{t('floor_tariff_title')}</h2>
                <p className="text-footnote text-fg-muted">{t('floor_tariff_intro')}</p>
              </div>
              <Button icon={Plus} onClick={() => setDialog({ tariff: null })}>
                {t('floor_tariff_add')}
              </Button>
            </div>
            <Notice message={notice} />
            {data.tariffs.length === 0 ? (
              <EmptyState icon={Banknote} title={t('floor_tariff_empty')} text={t('floor_tariff_emptyText')} />
            ) : (
              <DataTable caption={t('floor_tariff_title')}>
                <thead>
                  <tr>
                    <Th>{t('floor_tariff_scope')}</Th>
                    <Th>{t('floor_tariff_day')}</Th>
                    <Th numeric>{t('floor_tariff_price')}</Th>
                    <Th>{t('floor_tariff_period')}</Th>
                    <Th>{t('mgmt_actions')}</Th>
                  </tr>
                </thead>
                <tbody>
                  {data.tariffs.map((tariff) => (
                    <tr key={tariff.id}>
                      <Td className="font-bold">{scopeOf(tariff)}</Td>
                      <Td>
                        <Pill tone={tariff.day_type === 'holiday' ? 'danger' : tariff.day_type === 'weekend' ? 'warning' : 'neutral'}>{labelFor(t, 'floor_day_', tariff.day_type)}</Pill>
                      </Td>
                      <Td numeric className="whitespace-nowrap font-bold">
                        {Number(tariff.price) === 0 ? t('floor_tariff_free') : formatMoney(tariff.price, language)}
                      </Td>
                      <Td muted className="whitespace-nowrap">
                        {period(tariff)}
                      </Td>
                      <Td>
                        <div className="flex gap-1">
                          <IconButton icon={Pencil} label={t('floor_tariff_editAria', { scope: scopeOf(tariff), day: labelFor(t, 'floor_day_', tariff.day_type) })} onClick={() => setDialog({ tariff })} />
                          <IconButton icon={Trash2} label={t('floor_tariff_deleteAria', { scope: scopeOf(tariff), day: labelFor(t, 'floor_day_', tariff.day_type) })} onClick={() => setDeleting(tariff)} />
                        </div>
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </DataTable>
            )}
          </Card>

          <CalendarCard branchId={branchId} calendar={data.calendar} />

          {dialog && (
            <TariffDialog
              branchId={branchId}
              tariff={dialog.tariff}
              places={places}
              halls={(halls.data ?? []).map((hall) => ({ id: hall.id, name: hall.name }))}
              onClose={() => setDialog(null)}
              onSaved={() => {
                setNotice(dialog.tariff ? t('floor_tariff_saved') : t('floor_tariff_created'));
                setDialog(null);
              }}
            />
          )}
          {deleting && (
            <ConfirmDialog
              title={t('floor_tariff_deleteTitle')}
              text={t('floor_tariff_deleteText')}
              confirmLabel={t('mgmt_delete')}
              busy={remove.isPending}
              error={deleteError}
              onClose={() => {
                setDeleting(null);
                setDeleteError(null);
              }}
              onConfirm={() =>
                remove.mutate(deleting.id, {
                  onSuccess: () => {
                    setDeleting(null);
                    setNotice(t('floor_tariff_deleted'));
                  },
                  onError: (error) => setDeleteError(errorText(error, t)),
                })
              }
            />
          )}
        </div>
      )}
    </Gate>
  );
}
