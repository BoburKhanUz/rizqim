'use client';

import { useState } from 'react';
import { Armchair, Pencil, Percent, Plus, Trash2 } from 'lucide-react';
import { Button, Card, EmptyState, IconButton, formatMoney } from '@rizqim/ui';
import { useDeleteDiningTable, useDeleteHall, useHalls, useUpdateDiningTable, useUpdateHall, type DiningTable, type Hall } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { ConfirmDialog } from '../management/ConfirmDialog';
import { errorText } from '../management/errors';
import { Notice } from '../management/Notice';
import { Pill } from '../management/Pill';
import { Switch } from '../menu/Switch';
import { Gate } from '../pos/Gate';
import { labelFor } from '../reports/format';
import { DataTable, Td, Th } from '../reports/ui';
import { HallDialog } from './HallDialog';
import { HallFeeDialog } from './HallFeeDialog';
import { TableDialog } from './TableDialog';

type Dialog =
  | { kind: 'hall'; hall: Hall | null }
  | { kind: 'table'; hall: Hall; table: DiningTable | null }
  | { kind: 'fee'; hall: Hall }
  | { kind: 'delete-hall'; hall: Hall }
  | { kind: 'delete-table'; table: DiningTable };

// Halls and their places (tables, rooms, tapchans) of one branch. A hall switched off is a seasonal zone: it leaves the POS map.
export function HallsPanel({ branchId, canEdit, canSettings }: { branchId: number; canEdit: boolean; canSettings: boolean }) {
  const { t } = useI18n();
  const halls = useHalls(branchId);
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const updateHall = useUpdateHall();
  const updateTable = useUpdateDiningTable();
  const removeHall = useDeleteHall();
  const removeTable = useDeleteDiningTable();

  const close = () => {
    setDialog(null);
    setDeleteError(null);
  };
  const done = (message: string) => {
    setNotice(message);
    close();
  };

  return (
    <Gate query={halls} count={3}>
      {(data) => (
        <div className="flex flex-col gap-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-footnote text-fg-muted">{canEdit ? t('floor_halls_intro') : t('floor_readOnly')}</p>
            {canEdit && (
              <Button icon={Plus} onClick={() => setDialog({ kind: 'hall', hall: null })}>
                {t('floor_halls_add')}
              </Button>
            )}
          </div>
          <Notice message={notice} />
          {actionError && (
            <p role="alert" className="rounded-control bg-danger-soft px-3 py-2.5 text-footnote font-bold text-danger-soft-fg">
              {actionError}
            </p>
          )}

          {data.length === 0 ? (
            <EmptyState icon={Armchair} title={t('floor_halls_empty')} text={t('floor_halls_emptyText')} action={canEdit ? <Button icon={Plus} onClick={() => setDialog({ kind: 'hall', hall: null })}>{t('floor_halls_add')}</Button> : undefined} />
          ) : (
            data.map((hall) => (
              <Card key={hall.id} className="flex flex-col gap-3" role="group" aria-label={hall.name}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div className="flex min-w-0 flex-wrap items-center gap-2">
                    <h2 className="text-headline font-bold text-fg">{hall.name}</h2>
                    {!hall.is_active && <Pill tone="warning">{t('floor_hall_closed')}</Pill>}
                    <Pill tone="neutral">{hall.service_fee ? t('floor_hall_feeOwn', { percent: Number(hall.service_fee.service_charge_percent) }) : t('floor_hall_feeBranch')}</Pill>
                  </div>
                  <div className="flex flex-wrap items-center gap-1">
                    {canEdit && (
                      <>
                        <span className="flex items-center gap-1 text-footnote text-fg-muted">
                          {t('floor_hall_open')}
                          <Switch
                            checked={hall.is_active}
                            label={t('floor_hall_openAria', { name: hall.name })}
                            disabled={updateHall.isPending}
                            onChange={(checked) => {
                              setActionError(null);
                              updateHall.mutate({ id: hall.id, patch: { is_active: checked } }, { onSuccess: () => setNotice(checked ? t('floor_hall_opened', { name: hall.name }) : t('floor_hall_closedNote', { name: hall.name })), onError: (error) => setActionError(errorText(error, t)) });
                            }}
                          />
                        </span>
                        <IconButton icon={Pencil} label={t('floor_hall_editAria', { name: hall.name })} onClick={() => setDialog({ kind: 'hall', hall })} />
                      </>
                    )}
                    {canSettings && <IconButton icon={Percent} label={t('floor_hall_feeAria', { name: hall.name })} onClick={() => setDialog({ kind: 'fee', hall })} />}
                    {canEdit && <IconButton icon={Trash2} label={t('floor_hall_deleteAria', { name: hall.name })} onClick={() => setDialog({ kind: 'delete-hall', hall })} />}
                  </div>
                </div>

                {hall.tables.length === 0 ? (
                  <p className="text-footnote text-fg-muted">{t('floor_tables_empty')}</p>
                ) : (
                  <DataTable caption={t('floor_tables_caption', { name: hall.name })}>
                    <thead>
                      <tr>
                        <Th>{t('floor_table_name')}</Th>
                        <Th>{t('floor_table_kind')}</Th>
                        <Th numeric>{t('floor_table_seats')}</Th>
                        <Th>{t('floor_table_minSpend')}</Th>
                        <Th>{t('floor_table_active')}</Th>
                        {canEdit && <Th>{t('mgmt_actions')}</Th>}
                      </tr>
                    </thead>
                    <tbody>
                      {hall.tables.map((table) => (
                        <tr key={table.id}>
                          <Td className="font-bold">{table.name}</Td>
                          <Td>{labelFor(t, 'floor_kind_', table.kind)}</Td>
                          <Td numeric>{table.seats ?? '—'}</Td>
                          <Td className="whitespace-nowrap">
                            {table.min_spend && Number(table.min_spend) > 0 ? `${formatMoney(table.min_spend, 'uz')} · ${labelFor(t, 'floor_mode_', table.min_spend_mode)}` : '—'}
                          </Td>
                          <Td>
                            {canEdit ? (
                              <Switch
                                checked={table.is_active}
                                label={t('floor_table_activeAria', { name: table.name })}
                                disabled={updateTable.isPending}
                                onChange={(checked) => {
                                  setActionError(null);
                                  updateTable.mutate({ id: table.id, patch: { is_active: checked } }, { onError: (error) => setActionError(errorText(error, t)) });
                                }}
                              />
                            ) : (
                              <Pill tone={table.is_active ? 'success' : 'neutral'}>{table.is_active ? t('floor_yes') : t('floor_no')}</Pill>
                            )}
                          </Td>
                          {canEdit && (
                            <Td>
                              <div className="flex gap-1">
                                <IconButton icon={Pencil} label={t('floor_table_editAria', { name: table.name })} onClick={() => setDialog({ kind: 'table', hall, table })} />
                                <IconButton icon={Trash2} label={t('floor_table_deleteAria', { name: table.name })} onClick={() => setDialog({ kind: 'delete-table', table })} />
                              </div>
                            </Td>
                          )}
                        </tr>
                      ))}
                    </tbody>
                  </DataTable>
                )}
                {canEdit && (
                  <div>
                    <Button variant="secondary" icon={Plus} onClick={() => setDialog({ kind: 'table', hall, table: null })}>
                      {t('floor_table_add')}
                    </Button>
                  </div>
                )}
              </Card>
            ))
          )}

          {dialog?.kind === 'hall' && <HallDialog branchId={branchId} hall={dialog.hall} onClose={close} onSaved={(hall) => done(dialog.hall ? t('floor_hall_saved', { name: hall.name }) : t('floor_hall_created', { name: hall.name }))} />}
          {dialog?.kind === 'table' && <TableDialog halls={data} hall={dialog.hall} table={dialog.table} onClose={close} onSaved={(table) => done(dialog.table ? t('floor_table_saved', { name: table.name }) : t('floor_table_created', { name: table.name }))} />}
          {dialog?.kind === 'fee' && <HallFeeDialog hall={dialog.hall} onClose={close} onSaved={() => done(t('floor_fee_saved', { name: dialog.hall.name }))} />}
          {dialog?.kind === 'delete-hall' && (
            <ConfirmDialog
              title={t('floor_hall_deleteTitle', { name: dialog.hall.name })}
              text={t('floor_hall_deleteText')}
              confirmLabel={t('mgmt_delete')}
              busy={removeHall.isPending}
              error={deleteError}
              onClose={close}
              onConfirm={() => {
                setDeleteError(null);
                removeHall.mutate(dialog.hall.id, { onSuccess: () => done(t('floor_hall_deleted', { name: dialog.hall.name })), onError: (error) => setDeleteError(errorText(error, t)) });
              }}
            />
          )}
          {dialog?.kind === 'delete-table' && (
            <ConfirmDialog
              title={t('floor_table_deleteTitle', { name: dialog.table.name })}
              text={t('floor_table_deleteText')}
              confirmLabel={t('mgmt_delete')}
              busy={removeTable.isPending}
              error={deleteError}
              onClose={close}
              onConfirm={() => {
                setDeleteError(null);
                removeTable.mutate(dialog.table.id, { onSuccess: () => done(t('floor_table_deleted', { name: dialog.table.name })), onError: (error) => setDeleteError(errorText(error, t)) });
              }}
            />
          )}
        </div>
      )}
    </Gate>
  );
}
