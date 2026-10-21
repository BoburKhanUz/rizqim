'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Button, Card, EmptyState, InlineError, CardSkeletons, buttonClasses } from '@rizqim/ui';
import { Layers } from 'lucide-react';
import { useModifierGroups, useSyncProductModifierGroups, type Product } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { ErrorBanner, SuccessNote } from './ErrorBanner';
import { describeError } from './serverError';
import { Switch } from './Switch';

// Which modifier groups a product offers. The groups themselves are made on the "Modifikatorlar" tab; here they are
// attached to (or taken off) this product. The product keeps each group's own rules.
export function ProductModifiers({ product, canManage }: { product: Product; canManage: boolean }) {
  const { t } = useI18n();
  const query = useModifierGroups();
  if (query.isPending) return <CardSkeletons count={1} lines={3} label={t('loading')} />;
  if (query.isError) return <InlineError title={t('loadErrorTitle')} message={describeError(query.error, t).message} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;
  // Remounted when the groups are refetched, so the switches show what the server stored
  return <Switches key={query.dataUpdatedAt} product={product} groups={query.data} canManage={canManage} />;
}

function Switches({ product, groups, canManage }: { product: Product; groups: NonNullable<ReturnType<typeof useModifierGroups>['data']>; canManage: boolean }) {
  const { t } = useI18n();
  const [attached, setAttached] = useState<Set<number>>(() => new Set(groups.filter((group) => group.product_ids.includes(product.id)).map((group) => group.id)));
  const [saved, setSaved] = useState(false);
  const sync = useSyncProductModifierGroups();

  if (groups.length === 0) {
    return (
      <EmptyState
        icon={Layers}
        title={t('menu_groups_empty_title')}
        text={t('menu_product_groups_empty')}
        action={canManage ? <Link href="/menu/groups/new" className={buttonClasses({ variant: 'secondary' })}>{t('menu_add_group')}</Link> : undefined}
      />
    );
  }

  const toggle = (id: number, on: boolean) => {
    setSaved(false);
    setAttached((current) => {
      const next = new Set(current);
      if (on) next.add(id);
      else next.delete(id);
      return next;
    });
  };
  const save = () => {
    setSaved(false);
    // In the order of the list; nothing else is sent (each group keeps its own rules for this product)
    const chosen = groups.filter((group) => attached.has(group.id)).map((group, position) => ({ modifier_group_id: group.id, sort_order: position }));
    sync.mutate({ productId: product.id, groups: chosen }, { onSuccess: () => setSaved(true) });
  };
  const failure = sync.error ? describeError(sync.error, t) : null;

  return (
    <div className="flex flex-col gap-3">
      <ul className="flex flex-col gap-2">
        {groups.map((group) => (
          <li key={group.id}>
            <Card className="flex items-center gap-3">
              <div className="min-w-0 flex-1">
                <p className="text-body font-bold text-fg">
                  {group.name}
                  {!group.is_active && <span className="ml-2 text-footnote font-normal text-fg-muted">({t('menu_status_inactive')})</span>}
                </p>
                <p className="truncate text-footnote text-fg-muted">{group.modifiers.map((modifier) => modifier.name).join(', ')}</p>
              </div>
              <Switch checked={attached.has(group.id)} onChange={(on) => toggle(group.id, on)} disabled={!canManage || sync.isPending} label={`${t('menu_attach_group')}: ${group.name}`} />
            </Card>
          </li>
        ))}
      </ul>
      {failure && <ErrorBanner message={failure.message} details={Object.values(failure.fields)} />}
      {saved && <SuccessNote>{t('menu_saved')}</SuccessNote>}
      {canManage && (
        <div>
          <Button onClick={save} loading={sync.isPending}>
            {t('menu_save_groups')}
          </Button>
        </div>
      )}
    </div>
  );
}
