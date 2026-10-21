'use client';

import { useMemo, useState } from 'react';
import { Search } from 'lucide-react';
import { Button, Card, CardSkeletons, fieldClass, InlineError } from '@rizqim/ui';
import { useModifierGroups, useOwnerProducts, useSyncProductModifierGroups, type ModifierGroup } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { ErrorBanner, SuccessNote } from './ErrorBanner';
import { describeError } from './serverError';
import { Switch } from './Switch';

// The products that offer a group. The API attaches groups per product (it replaces a product's list), so a change here
// sends, for each product that changed, that product's whole list with this group put in or taken out.
export function GroupProducts({ group, canManage }: { group: ModifierGroup; canManage: boolean }) {
  const { t } = useI18n();
  const products = useOwnerProducts();
  const groups = useModifierGroups();
  const sync = useSyncProductModifierGroups();
  const [chosen, setChosen] = useState<Set<number>>(() => new Set(group.product_ids));
  const [search, setSearch] = useState('');
  const [saved, setSaved] = useState(false);
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const shown = useMemo(() => {
    const needle = search.trim().toLowerCase();
    return (products.data ?? []).filter((product) => needle === '' || product.name.toLowerCase().includes(needle));
  }, [products.data, search]);

  if (products.isPending || groups.isPending) return <CardSkeletons count={1} lines={3} label={t('loading')} />;
  if (products.isError || groups.isError) {
    const error = products.error ?? groups.error;
    return (
      <InlineError
        title={t('loadErrorTitle')}
        message={describeError(error, t).message}
        retryLabel={t('retry')}
        retrying={products.isFetching || groups.isFetching}
        onRetry={() => {
          void products.refetch();
          void groups.refetch();
        }}
      />
    );
  }

  const initial = new Set(group.product_ids);
  const changed = (products.data ?? []).filter((product) => chosen.has(product.id) !== initial.has(product.id));

  const save = async () => {
    setSaved(false);
    setFailure(null);
    setBusy(true);
    try {
      for (const product of changed) {
        const list = groups.data
          .filter((other) => (other.id === group.id ? chosen.has(product.id) : other.product_ids.includes(product.id)))
          .map((other, position) => ({ modifier_group_id: other.id, sort_order: position }));
        await sync.mutateAsync({ productId: product.id, groups: list });
      }
      setSaved(true);
    } catch (error) {
      setFailure(describeError(error, t).message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="flex flex-col gap-3">
      <label className="relative">
        <span className="sr-only">{t('menu_search')}</span>
        <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-fg-muted" />
        <input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={t('menu_search')} className={`${fieldClass} pl-10`} />
      </label>
      <ul className="flex flex-col gap-2">
        {shown.map((product) => (
          <li key={product.id}>
            <Card className="flex items-center gap-3">
              <p className="min-w-0 flex-1 truncate text-body font-bold text-fg">{product.name}</p>
              <Switch
                checked={chosen.has(product.id)}
                disabled={!canManage || busy}
                label={`${t('menu_offer_in')}: ${product.name}`}
                onChange={(on) => {
                  setSaved(false);
                  setChosen((current) => {
                    const next = new Set(current);
                    if (on) next.add(product.id);
                    else next.delete(product.id);
                    return next;
                  });
                }}
              />
            </Card>
          </li>
        ))}
      </ul>
      {failure && <ErrorBanner message={failure} />}
      {saved && <SuccessNote>{t('menu_saved')}</SuccessNote>}
      {canManage && (
        <div>
          <Button onClick={() => void save()} loading={busy} disabled={changed.length === 0}>
            {t('menu_save_products')}
          </Button>
        </div>
      )}
    </div>
  );
}
