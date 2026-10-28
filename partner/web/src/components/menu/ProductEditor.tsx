'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { ArrowLeft, UtensilsCrossed } from 'lucide-react';
import { buttonClasses, CardSkeletons, EmptyState, InlineError, Section, PageHeader } from '@rizqim/ui';
import { useOwnerCategories, useOwnerProducts } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { useMenuAccess } from './access';
import { BranchOverrides } from './BranchOverrides';
import { ChannelPrices } from './ChannelPrices';
import { ProductForm } from './ProductForm';
import { ProductModifiers } from './ProductModifiers';
import { describeError } from './serverError';

// /menu/new and /menu/{id}: one product with its variants, and (once it exists) its modifier groups, channel prices
// and branch overrides. Each part is saved on its own, because each goes to its own endpoint.
export function ProductEditor({ id }: { id: string }) {
  const { t } = useI18n();
  const router = useRouter();
  const access = useMenuAccess();
  const isNew = id === 'new';
  const products = useOwnerProducts({ enabled: !isNew });
  const categories = useOwnerCategories();
  const canManage = access.can('menu.manage');

  const back = (
    <Link href="/menu" className={buttonClasses({ variant: 'ghost' })}>
      <ArrowLeft aria-hidden="true" className="size-[18px]" />
      {t('nav_menu')}
    </Link>
  );

  const failed = (isNew ? null : products.error) ?? categories.error;
  const title = isNew ? t('menu_add_product') : t('menu_edit_product');
  if (failed) {
    return (
      <>
        <PageHeader title={title} action={back} />
        <InlineError
          title={t('loadErrorTitle')}
          message={describeError(failed, t).message}
          retryLabel={t('retry')}
          retrying={products.isFetching || categories.isFetching}
          onRetry={() => {
            void products.refetch();
            void categories.refetch();
          }}
        />
      </>
    );
  }
  if ((!isNew && products.isPending) || categories.isPending) {
    return (
      <>
        <PageHeader title={title} action={back} />
        <CardSkeletons count={3} lines={3} label={t('loading')} />
      </>
    );
  }

  const product = isNew ? null : (products.data?.find((item) => String(item.id) === id) ?? null);
  if (!isNew && !product) {
    return (
      <>
        <PageHeader title={t('menu_edit_product')} action={back} />
        <EmptyState icon={UtensilsCrossed} title={t('menu_product_not_found')} text={t('menu_product_not_found_text')} action={<Link href="/menu" className={buttonClasses()}>{t('nav_menu')}</Link>} />
      </>
    );
  }

  return (
    <>
      <PageHeader title={product ? product.name : t('menu_add_product')} subtitle={product ? t('menu_edit_product') : undefined} action={back} />
      <div className="flex flex-col gap-8">
        <ProductForm
          // A fresh form for another product
          key={product?.id ?? 'new'}
          product={product}
          categories={categories.data ?? []}
          canManage={canManage}
          onCreated={(created) => router.replace(`/menu/${created.id}`)}
        />
        {product ? (
          <>
            <Section title={t('menu_section_modifiers')}>
              <ProductModifiers product={product} canManage={canManage} />
            </Section>
            <Section title={t('menu_section_channels')}>
              <ChannelPrices product={product} canManage={canManage} />
            </Section>
            <Section title={t('menu_section_branches')}>
              <BranchOverrides product={product} canManage={canManage} />
            </Section>
          </>
        ) : (
          <p className="text-footnote text-fg-muted">{t('menu_save_first')}</p>
        )}
      </div>
    </>
  );
}
