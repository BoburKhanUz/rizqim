'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { Pencil, Search, Trash2, UtensilsCrossed } from 'lucide-react';
import { buttonClasses, Button, CardSkeletons, Card, Dialog, EmptyState, fieldClass, formatMoney, IconButton, InlineError, Switch } from '@rizqim/ui';
import { unitLabel, useDeleteProduct, useOwnerProducts, useToggleProductActive, type Language, type Product } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { ErrorBanner } from './ErrorBanner';
import { ProductThumb } from './ProductThumb';
import { describeError } from './serverError';

type StatusFilter = 'all' | 'active' | 'inactive';

// The price of a product as the list prints it: one variant: its price; several: the range. Display only.
function priceText(product: Product, language: Language): string {
  const variants = product.variants ?? [];
  if (variants.length === 0) return '—';
  const prices = variants.map((variant) => Number(variant.price)).filter((price) => Number.isFinite(price));
  const lowest = Math.min(...prices);
  const highest = Math.max(...prices);
  const unit = variants[0]?.unit;
  const suffix = unit && unit !== 'piece' ? ` / ${unitLabel(unit, language)}` : '';
  return lowest === highest ? `${formatMoney(lowest, language)}${suffix}` : `${formatMoney(lowest, language)} – ${formatMoney(highest, language)}${suffix}`;
}

export function ProductList({ canManage }: { canManage: boolean }) {
  const { t, language } = useI18n();
  const query = useOwnerProducts();
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('all');
  const [status, setStatus] = useState<StatusFilter>('all');
  const [deleting, setDeleting] = useState<Product | null>(null);
  const toggle = useToggleProductActive();
  const remove = useDeleteProduct({ onSuccess: () => setDeleting(null) });

  const products = useMemo(() => query.data ?? [], [query.data]);
  const categories = useMemo(() => {
    const seen = new Map<number, string>();
    for (const product of products) if (product.category) seen.set(product.category_id, product.category.name);
    return Array.from(seen, ([id, name]) => ({ id, name }));
  }, [products]);

  const groups = useMemo(() => {
    const needle = search.trim().toLowerCase();
    const shown = products.filter(
      (product) =>
        (needle === '' || product.name.toLowerCase().includes(needle)) &&
        (category === 'all' || String(product.category_id) === category) &&
        (status === 'all' || (status === 'active') === product.is_active),
    );
    // The server sends them by category, then by sort order: keep that order
    const byCategory = new Map<number, { id: number; name: string; products: Product[] }>();
    for (const product of shown) {
      const entry = byCategory.get(product.category_id) ?? { id: product.category_id, name: product.category?.name ?? t('menu_no_category'), products: [] };
      entry.products.push(product);
      byCategory.set(product.category_id, entry);
    }
    return Array.from(byCategory.values());
  }, [products, search, category, status, t]);

  if (query.isPending) return <CardSkeletons count={4} lines={2} label={t('loading')} />;
  if (query.isError) {
    const view = describeError(query.error, t);
    return <InlineError title={t('loadErrorTitle')} message={view.message} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;
  }
  if (products.length === 0) {
    return (
      <EmptyState
        icon={UtensilsCrossed}
        title={t('menu_empty_title')}
        text={t('menu_empty_text')}
        action={canManage ? <Link href="/menu/new" className={buttonClasses()}>{t('menu_add_product')}</Link> : undefined}
      />
    );
  }

  const failure = toggle.error ?? remove.error;
  const failureView = failure ? describeError(failure, t) : null;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end gap-3">
        <label className="relative min-w-52 flex-1">
          <span className="sr-only">{t('menu_search')}</span>
          <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-fg-muted" />
          <input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={t('menu_search')} className={`${fieldClass} pl-10`} />
        </label>
        <label className="flex flex-col gap-1.5">
          <span className="text-footnote font-bold text-fg-muted">{t('menu_category')}</span>
          <select value={category} onChange={(event) => setCategory(event.target.value)} className={fieldClass}>
            <option value="all">{t('menu_all_categories')}</option>
            {categories.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1.5">
          <span className="text-footnote font-bold text-fg-muted">{t('menu_status')}</span>
          <select value={status} onChange={(event) => setStatus(event.target.value as StatusFilter)} className={fieldClass}>
            <option value="all">{t('menu_status_all')}</option>
            <option value="active">{t('menu_status_active')}</option>
            <option value="inactive">{t('menu_status_inactive')}</option>
          </select>
        </label>
      </div>

      {failureView && <ErrorBanner message={failureView.message} />}

      {groups.length === 0 ? (
        <EmptyState icon={Search} title={t('menu_nothing_found')} text={t('menu_nothing_found_text')} />
      ) : (
        groups.map((group) => (
          <section key={group.id} aria-label={group.name} className="flex flex-col gap-2">
            <h2 className="px-1 text-footnote font-bold text-fg-muted">
              {group.name} · {group.products.length}
            </h2>
            <ul className="flex flex-col gap-2">
              {group.products.map((product) => (
                <li key={product.id}>
                  <Card className="flex flex-wrap items-center gap-3 sm:flex-nowrap">
                    <ProductThumb product={product} />
                    <div className="min-w-0 flex-1">
                      <p className="flex flex-wrap items-center gap-x-2 text-body font-bold text-fg">
                        <Link href={`/menu/${product.id}`} className="truncate hover:underline">
                          {product.name}
                        </Link>
                        {product.batch_tracked && <span className="rounded-control bg-info-soft px-2 py-0.5 text-caption font-bold text-info-soft-fg">{t('menu_batch_badge')}</span>}
                        {!product.is_active && <span className="rounded-control bg-surface-2 px-2 py-0.5 text-caption font-bold text-fg-muted">{t('menu_status_inactive')}</span>}
                      </p>
                      <p className="text-footnote text-fg-muted">
                        {t('menu_variants_count', { n: (product.variants ?? []).length })} · {priceText(product, language)}
                      </p>
                    </div>
                    <span className="hidden text-footnote text-fg-muted lg:block">#{product.sort_order}</span>
                    {canManage ? (
                      <>
                        <Switch
                          checked={product.is_active}
                          label={`${product.name}: ${product.is_active ? t('menu_status_active') : t('menu_status_inactive')}`}
                          disabled={toggle.isPending && toggle.variables === product.id}
                          onChange={() => toggle.mutate(product.id)}
                        />
                        <Link href={`/menu/${product.id}`} aria-label={`${t('menu_edit')}: ${product.name}`} className={buttonClasses({ variant: 'secondary' })}>
                          <Pencil aria-hidden="true" className="size-[18px]" />
                          <span className="hidden sm:inline">{t('menu_edit')}</span>
                        </Link>
                        <IconButton icon={Trash2} label={`${t('menu_delete')}: ${product.name}`} onClick={() => setDeleting(product)} />
                      </>
                    ) : (
                      <Link href={`/menu/${product.id}`} className={buttonClasses({ variant: 'secondary' })}>
                        {t('menu_open')}
                      </Link>
                    )}
                  </Card>
                </li>
              ))}
            </ul>
          </section>
        ))
      )}

      <Dialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        variant="center"
        role="alertdialog"
        title={t('menu_delete_product_title')}
        description={deleting ? t('menu_delete_product_text', { name: deleting.name }) : undefined}
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
