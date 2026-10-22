'use client';

import { useMemo, useState, type FormEvent } from 'react';
import { Plus, Trash2, Soup } from 'lucide-react';
import { Button, Card, Dialog, EmptyState, IconButton } from '@rizqim/ui';
import { useAutoGuestItems, useCreateAutoGuestItem, useDeleteAutoGuestItem, useHalls, useOwnerProducts, useUpdateAutoGuestItem } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { ConfirmDialog } from '../management/ConfirmDialog';
import { errorText } from '../management/errors';
import { NumberField, parseDecimal } from '../management/fields';
import { FormError, Notice } from '../management/Notice';
import { Switch } from '../menu/Switch';
import { Gate } from '../pos/Gate';
import { SelectField } from '../pos/fields';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';
import { DataTable, Td, Th } from '../reports/ui';

// "Choy-non": what the POS adds to a room's order for every guest. One rule per dish and hall (a hall of its own, or all).
export function GuestItemsPanel({ branchId }: { branchId: number }) {
  const { t } = useI18n();
  const items = useAutoGuestItems(branchId);
  const halls = useHalls(branchId);
  const update = useUpdateAutoGuestItem();
  const remove = useDeleteAutoGuestItem();
  const [adding, setAdding] = useState(false);
  const [deleting, setDeleting] = useState<{ id: number; name: string } | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [failure, setFailure] = useState<string | null>(null);
  const hallName = (id: number | null) => (id === null ? t('floor_guest_allHalls') : ((halls.data ?? []).find((hall) => hall.id === id)?.name ?? `#${id}`));

  return (
    <Gate query={items} count={2}>
      {(data) => (
        <Card className="flex flex-col gap-3">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="text-headline font-bold text-fg">{t('floor_guest_title')}</h2>
              <p className="text-footnote text-fg-muted">{t('floor_guest_intro')}</p>
            </div>
            <Button icon={Plus} onClick={() => setAdding(true)}>
              {t('floor_guest_add')}
            </Button>
          </div>
          <Notice message={notice} />
          <FormError message={failure} />
          {data.length === 0 ? (
            <EmptyState icon={Soup} title={t('floor_guest_empty')} text={t('floor_guest_emptyText')} />
          ) : (
            <DataTable caption={t('floor_guest_title')}>
              <thead>
                <tr>
                  <Th>{t('floor_guest_item')}</Th>
                  <Th numeric>{t('floor_guest_qty')}</Th>
                  <Th>{t('floor_table_hall')}</Th>
                  <Th>{t('floor_table_active')}</Th>
                  <Th>{t('mgmt_actions')}</Th>
                </tr>
              </thead>
              <tbody>
                {data.map((item) => {
                  const name = [item.product_name, item.variant_name].filter(Boolean).join(' · ') || `#${item.product_variant_id}`;
                  return (
                    <tr key={item.id}>
                      <Td className="font-bold">{name}</Td>
                      <Td numeric>{item.quantity_per_guest}</Td>
                      <Td>{hallName(item.hall_id)}</Td>
                      <Td>
                        <Switch
                          checked={item.is_active}
                          label={t('floor_guest_activeAria', { name })}
                          disabled={update.isPending}
                          onChange={(checked) => {
                            setFailure(null);
                            update.mutate({ id: item.id, patch: { is_active: checked } }, { onError: (error) => setFailure(errorText(error, t)) });
                          }}
                        />
                      </Td>
                      <Td>
                        <IconButton icon={Trash2} label={t('floor_guest_deleteAria', { name })} onClick={() => setDeleting({ id: item.id, name })} />
                      </Td>
                    </tr>
                  );
                })}
              </tbody>
            </DataTable>
          )}

          {adding && (
            <GuestItemDialog
              branchId={branchId}
              halls={(halls.data ?? []).map((hall) => ({ id: hall.id, name: hall.name }))}
              onClose={() => setAdding(false)}
              onSaved={() => {
                setAdding(false);
                setNotice(t('floor_guest_created'));
              }}
            />
          )}
          {deleting && (
            <ConfirmDialog
              title={t('floor_guest_deleteTitle', { name: deleting.name })}
              text={t('floor_guest_deleteText')}
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
                    setNotice(t('floor_guest_deleted'));
                  },
                  onError: (error) => setDeleteError(errorText(error, t)),
                })
              }
            />
          )}
        </Card>
      )}
    </Gate>
  );
}

function GuestItemDialog({ branchId, halls, onClose, onSaved }: { branchId: number; halls: Array<{ id: number; name: string }>; onClose: () => void; onSaved: () => void }) {
  const { t } = useI18n();
  const products = useOwnerProducts();
  const [variantId, setVariantId] = useState('');
  const [hallId, setHallId] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const create = useCreateAutoGuestItem();

  // Every variant of the menu as "Choy · 1 choynak"
  const options = useMemo(
    () => (products.data ?? []).flatMap((product) => (product.variants ?? []).map((variant) => ({ value: String(variant.id), label: [product.name, variant.name].filter(Boolean).join(' · ') }))),
    [products.data],
  );

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const problems: Record<string, string> = {};
    if (variantId === '') problems.product_variant_id = t('floor_err_variant');
    const amount = parseDecimal(quantity);
    if (amount === undefined || amount === null || amount <= 0) problems.quantity_per_guest = t('floor_err_quantity');
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;
    create.mutate(
      { branch_id: branchId, product_variant_id: Number(variantId), quantity_per_guest: amount as number, hall_id: hallId === '' ? null : Number(hallId), is_active: true },
      { onSuccess: onSaved, onError: (error) => setErrors(sortServerErrors(error, ['product_variant_id', 'quantity_per_guest', 'hall_id', 'branch_id'], t)) },
    );
  };

  return (
    <Dialog open title={t('floor_guest_newTitle')} busy={create.isPending} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <SelectField label={t('floor_guest_item')} value={variantId} onChange={setVariantId} options={[{ value: '', label: t('floor_guest_chooseItem') }, ...options]} error={local.product_variant_id ?? errors.fields.product_variant_id} />
        <NumberField label={t('floor_guest_qty')} hint={t('floor_guest_qtyHint')} value={quantity} onChange={setQuantity} error={local.quantity_per_guest ?? errors.fields.quantity_per_guest} />
        <SelectField label={t('floor_table_hall')} value={hallId} onChange={setHallId} options={[{ value: '', label: t('floor_guest_allHalls') }, ...halls.map((hall) => ({ value: String(hall.id), label: hall.name }))]} error={errors.fields.hall_id} />
        <FormError message={errors.form} />
        <div className="flex flex-wrap justify-end gap-3">
          <Button variant="secondary" onClick={onClose} disabled={create.isPending}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={create.isPending}>
            {t('mgmt_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
