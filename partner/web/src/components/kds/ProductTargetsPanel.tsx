'use client';

import { useState, type FormEvent } from 'react';
import { Hourglass, Trash2 } from 'lucide-react';
import { Button, Card, EmptyState, FormError, IconButton, Notice } from '@rizqim/ui';
import { useOwnerProducts } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from '../management/errors';
import { Gate } from '../pos/Gate';
import { SelectField, TextField } from '../pos/fields';
import { DataTable, Td, Th } from '../reports/ui';
import { formatDuration, parseTarget } from './format';
import { useKdsTargets, useSaveProductTargets } from './hooks';

const NONE = '';

// A dish's own time target (P6-33, module kds_expo) over its station's: a big burger takes longer on the grill. Store-wide;
// removing it puts the station's target back.
export function ProductTargetsPanel({ branchId }: { branchId: number }) {
  const { t } = useI18n();
  const targets = useKdsTargets(branchId);
  const products = useOwnerProducts();
  const save = useSaveProductTargets(branchId);
  const [productId, setProductId] = useState<string>(NONE);
  const [seconds, setSeconds] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  return (
    <Gate query={targets} count={1}>
      {(page) => {
        const submit = (event: FormEvent) => {
          event.preventDefault();
          setNotice(null);
          const value = parseTarget(seconds, page.limits);
          if (productId === NONE || value === undefined || value === null) {
            setError(t('kds_err_target', { min: page.limits.min, max: page.limits.max }));
            return;
          }
          setError(null);
          save.mutate([{ product_id: Number(productId), target_seconds: value }], {
            onSuccess: () => {
              setNotice(t('kds_products_saved'));
              setProductId(NONE);
              setSeconds('');
            },
            onError: (failure) => setError(errorText(failure, t)),
          });
        };
        const remove = (id: number, name: string) => {
          setNotice(null);
          setError(null);
          save.mutate([{ product_id: id, target_seconds: null }], {
            onSuccess: () => setNotice(t('kds_products_removed', { name })),
            onError: (failure) => setError(errorText(failure, t)),
          });
        };

        return (
          <Card className="flex flex-col gap-3">
            <div>
              <h2 className="text-headline font-bold text-fg">{t('kds_products_title')}</h2>
              <p className="text-footnote text-fg-muted">{t('kds_products_intro')}</p>
            </div>
            <Notice message={notice} />
            <FormError message={error} />
            <form onSubmit={submit} noValidate className="flex flex-wrap items-end gap-3">
              <SelectField
                label={t('kds_products_dish')}
                value={productId}
                onChange={setProductId}
                className="sm:min-w-64"
                options={[{ value: NONE, label: t('kds_products_choose') }, ...(products.data ?? []).map((product) => ({ value: String(product.id), label: product.name }))]}
              />
              <TextField label={t('kds_products_seconds')} value={seconds} inputMode="numeric" onChange={setSeconds} className="w-32" />
              <Button type="submit" loading={save.isPending} disabled={productId === NONE}>
                {t('kds_products_add')}
              </Button>
            </form>
            {page.products.length === 0 ? (
              <EmptyState icon={Hourglass} title={t('kds_products_empty')} />
            ) : (
              <DataTable caption={t('kds_products_title')}>
                <thead>
                  <tr>
                    <Th>{t('kds_products_dish')}</Th>
                    <Th numeric>{t('kds_col_target')}</Th>
                    <Th>{t('mgmt_actions')}</Th>
                  </tr>
                </thead>
                <tbody>
                  {page.products.map((product) => (
                    <tr key={product.product_id}>
                      <Td className="font-bold">{product.name}</Td>
                      <Td numeric>{formatDuration(product.target_seconds, t)}</Td>
                      <Td>
                        <IconButton icon={Trash2} label={t('kds_products_removeAria', { name: product.name })} disabled={save.isPending} onClick={() => remove(product.product_id, product.name)} />
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </DataTable>
            )}
          </Card>
        );
      }}
    </Gate>
  );
}
