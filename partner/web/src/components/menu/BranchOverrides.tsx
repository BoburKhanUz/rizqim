'use client';

import { Card, CardSkeletons, InlineError } from '@rizqim/ui';
import { useBranchVariants, useSaveBranchVariant, type Product } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { BranchSelect, useBranchChoice } from './BranchChoice';
import { ErrorBanner } from './ErrorBanner';
import { describeError } from './serverError';
import { Switch } from './Switch';

// Which variants of a product a branch sells (GET/POST /owner/branch-variants). Switching one off here also puts it on
// that branch's stop-list.
export function BranchOverrides({ product, canManage }: { product: Product; canManage: boolean }) {
  const { t } = useI18n();
  const choice = useBranchChoice();
  const query = useBranchVariants(choice.branchId ?? undefined, { enabled: choice.branchId !== null });
  const save = useSaveBranchVariant();

  if (choice.isPending) return <CardSkeletons count={1} lines={2} label={t('loading')} />;
  if (choice.isError) return <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={choice.isFetching} onRetry={choice.refetch} />;
  if (choice.branchId === null) return <p className="text-body text-fg-muted">{t('menu_no_branches')}</p>;

  const variants = query.data?.find((entry) => entry.product_id === product.id)?.variants ?? [];
  const failure = save.error ? describeError(save.error, t) : null;

  return (
    <div className="flex flex-col gap-3">
      <p className="text-footnote text-fg-muted">{t('menu_branch_hint')}</p>
      <BranchSelect branches={choice.branches} value={choice.branchId} onChange={choice.choose} className="max-w-xs" />
      {query.isPending ? (
        <CardSkeletons count={1} lines={2} label={t('loading')} />
      ) : query.isError ? (
        <InlineError title={t('loadErrorTitle')} message={describeError(query.error, t).message} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />
      ) : (
        <ul className="flex flex-col gap-2">
          {variants.map((variant) => (
            <li key={variant.variant_id}>
              <Card className="flex items-center gap-3">
                <div className="min-w-0 flex-1">
                  <p className="text-body font-bold text-fg">{variant.name ?? t('menu_variant_default')}</p>
                  {!variant.is_active && variant.hidden_reason && <p className="text-footnote text-fg-muted">{variant.hidden_reason}</p>}
                </div>
                <Switch
                  checked={variant.is_active}
                  disabled={!canManage || save.isPending}
                  label={`${variant.name ?? t('menu_variant_default')}: ${t('menu_sold_in_branch')}`}
                  onChange={(isActive) => save.mutate({ branch_id: choice.branchId as number, product_id: product.id, product_variant_id: variant.variant_id, is_active: isActive })}
                />
              </Card>
            </li>
          ))}
        </ul>
      )}
      {failure && <ErrorBanner message={failure.message} />}
    </div>
  );
}
