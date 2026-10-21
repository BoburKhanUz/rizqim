'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Layers, Pencil, Trash2 } from 'lucide-react';
import { Button, buttonClasses, Card, CardSkeletons, Dialog, EmptyState, formatMoney, IconButton, InlineError } from '@rizqim/ui';
import { useDeleteModifierGroup, useModifierGroups, type ModifierGroup } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { ErrorBanner } from './ErrorBanner';
import { describeError } from './serverError';

export function ModifierGroupList({ canManage }: { canManage: boolean }) {
  const { t, language } = useI18n();
  const query = useModifierGroups();
  const [deleting, setDeleting] = useState<ModifierGroup | null>(null);
  const remove = useDeleteModifierGroup({ onSuccess: () => setDeleting(null) });

  if (query.isPending) return <CardSkeletons count={3} lines={2} label={t('loading')} />;
  if (query.isError) {
    return <InlineError title={t('loadErrorTitle')} message={describeError(query.error, t).message} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;
  }
  const groups = query.data;
  if (groups.length === 0) {
    return (
      <EmptyState
        icon={Layers}
        title={t('menu_groups_empty_title')}
        text={t('menu_groups_empty_text')}
        action={canManage ? <Link href="/menu/groups/new" className={buttonClasses()}>{t('menu_add_group')}</Link> : undefined}
      />
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {remove.error && <ErrorBanner message={describeError(remove.error, t).message} />}
      <ul className="flex flex-col gap-3">
        {groups.map((group) => {
          const rule =
            group.max_select === null
              ? t('menu_rule_min', { min: group.min_select })
              : group.min_select === group.max_select
                ? t('menu_rule_exact', { n: group.max_select })
                : t('menu_rule_range', { min: group.min_select, max: group.max_select });
          return (
            <li key={group.id}>
              <Card className="flex flex-col gap-3">
                <div className="flex flex-wrap items-center gap-2">
                  <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-2 text-body font-bold text-fg">
                      {group.name}
                      {group.is_required && <span className="rounded-control bg-warning-soft px-2 py-0.5 text-caption font-bold text-warning-soft-fg">{t('menu_required')}</span>}
                      {!group.is_active && <span className="rounded-control bg-surface-2 px-2 py-0.5 text-caption font-bold text-fg-muted">{t('menu_status_inactive')}</span>}
                    </p>
                    <p className="text-footnote text-fg-muted">
                      {rule} · {t('menu_group_products', { n: group.product_ids.length })}
                    </p>
                  </div>
                  {canManage && (
                    <>
                      <Link href={`/menu/groups/${group.id}`} aria-label={`${t('menu_edit')}: ${group.name}`} className={buttonClasses({ variant: 'secondary' })}>
                        <Pencil aria-hidden="true" className="size-[18px]" />
                        <span className="hidden sm:inline">{t('menu_edit')}</span>
                      </Link>
                      <IconButton icon={Trash2} label={`${t('menu_delete')}: ${group.name}`} onClick={() => setDeleting(group)} />
                    </>
                  )}
                </div>
                <ul className="flex flex-wrap gap-2">
                  {group.modifiers.map((modifier) => (
                    <li key={modifier.id} className={`rounded-control px-2.5 py-1 text-footnote ${modifier.is_active ? 'bg-surface-2 text-fg' : 'bg-surface-2 text-fg-muted line-through'}`}>
                      {modifier.name}
                      {Number(modifier.price_delta) > 0 && <span className="text-fg-muted"> +{formatMoney(modifier.price_delta, language)}</span>}
                    </li>
                  ))}
                </ul>
              </Card>
            </li>
          );
        })}
      </ul>

      <Dialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        variant="center"
        role="alertdialog"
        title={t('menu_delete_group_title')}
        description={deleting ? t('menu_delete_group_text', { name: deleting.name }) : undefined}
        busy={remove.isPending}
        closeLabel={t('menu_close')}
        footer={
          <>
            <Button variant="secondary" onClick={() => setDeleting(null)} disabled={remove.isPending}>
              {t('menu_cancel')}
            </Button>
            <Button variant="danger" loading={remove.isPending} onClick={() => deleting && remove.mutate(deleting.id)}>
              {t('menu_delete')}
            </Button>
          </>
        }
      />
    </div>
  );
}
