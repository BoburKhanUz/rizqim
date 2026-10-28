'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Plus } from 'lucide-react';
import { buttonClasses, PageHeader, Tabs } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { useMenuAccess } from './access';
import { ModifierGroupList } from './ModifierGroupList';
import { ProductList } from './ProductList';

type MenuTab = 'products' | 'groups';

// Menyu: taomlar va modifikator guruhlari (docs/MENU.md). Tahrirlash alohida sahifalarda: /menu/{id}, /menu/groups/{id}.
export function MenuView() {
  const { t } = useI18n();
  const access = useMenuAccess();
  const [tab, setTab] = useState<MenuTab>('products');
  const canManage = access.can('menu.manage');

  return (
    <>
      <PageHeader
        title={t('nav_menu')}
        subtitle={t('menu_subtitle')}
        action={
          canManage && (
            <Link href={tab === 'products' ? '/menu/new' : '/menu/groups/new'} className={buttonClasses({ variant: 'primary' })}>
              <Plus aria-hidden="true" className="size-[18px]" />
              {tab === 'products' ? t('menu_add_product') : t('menu_add_group')}
            </Link>
          )
        }
      />
      <Tabs
        idPrefix="menu"
        label={t('menu_sections')}
        value={tab}
        onChange={setTab}
        tabs={[
          { id: 'products', label: t('menu_tab_products') },
          { id: 'groups', label: t('menu_tab_groups') },
        ]}
      />
      <div role="tabpanel" id={`menu-panel-${tab}`} aria-labelledby={`menu-tab-${tab}`}>
        {tab === 'products' ? <ProductList canManage={canManage} /> : <ModifierGroupList canManage={canManage} />}
      </div>
    </>
  );
}
