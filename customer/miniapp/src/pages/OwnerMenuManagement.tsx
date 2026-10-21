import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Plus, Search, Pencil, Trash2, SearchX, PackageOpen, PackageX } from 'lucide-react';

import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred } from '../lib/twa';
import { showAlert, showConfirm } from '../lib/dialogs';
import { deleteProduct as removeProduct, fetchOwnerProducts, isMeasuredUnit, toggleProductActive, type MenuUnit, type Product } from '@rizqim/staff';
import { staffApi } from '../services/staffApi';
import { logApiError } from '../lib/apiError';
import { toAmount } from '../lib/money';
import { formatMoney } from '../lib/format';
import { Button, CardSkeletons, EmptyState, IconButton, InlineError, PageHeader, cx, fieldClass, ProductImage } from '../components/ui';
import { Pill } from '../components/staff/Pill';
import { Switch } from '../components/staff/Switch';
import { useLanguageStore } from '../store/languageStore';

// The owner's menu on the phone: the dishes, on sale or not, and a quick edit (the full editor is the partner web's)
export default function OwnerMenuManagement() {
  const navigate = useNavigate();
  const { t } = useLanguageStore();

  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [reloadKey, setReloadKey] = useState(0); // bumped by \"Qayta urinish\" after a failed load
  const [search, setSearch] = useState('');

  useEffect(() => {
    let cancelled = false;
    const fetchProducts = async () => {
      try {
        const list = await fetchOwnerProducts(staffApi);
        if (cancelled) return;
        setProducts(list);
        setError(false);
      } catch (err) {
        if (cancelled) return;
        logApiError('Failed to load products:', err);
        setError(true);
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    fetchProducts();
    return () => {
      cancelled = true;
    };
  }, [reloadKey]);

  const toggleActive = async (id: number) => {
    hapticFeedback('light');
    // Optimistic update
    setProducts(prev => prev.map(p => p.id === id ? { ...p, status: p.status === 'active' ? 'inactive' : 'active' } : p));
    try {
      await toggleProductActive(staffApi, id);
    } catch {
      // Revert on error
      setProducts(prev => prev.map(p => p.id === id ? { ...p, status: p.status === 'active' ? 'inactive' : 'active' } : p));
      notificationOccurred('error');
    }
  };

  const deleteProduct = async (product: Product) => {
    const confirmed = await showConfirm(t('ownerMenu_deleteText', { name: product.name }), {
      title: t('ownerMenu_deleteTitle'),
      confirmText: t('ownerMenu_delete'),
      destructive: true,
    });
    if (!confirmed) return;

    hapticFeedback('medium');
    try {
      await removeProduct(staffApi, product.id);
      setProducts(prev => prev.filter(p => p.id !== product.id));
      notificationOccurred('success');
    } catch {
      void showAlert(t('ownerMenu_deleteFailed'));
      notificationOccurred('error');
    }
  };


  const filteredProducts = products.filter(p => p.name.toLowerCase().includes(search.toLowerCase()));

  if (loading && products.length === 0) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title={t('ownerMenu_title')} />
        <CardSkeletons count={3} lines={3} label={t('ownerMenu_loading')} />
      </div>
    );
  }
  if (error) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title={t('ownerMenu_title')} />
        <InlineError
          onRetry={() => {
            setLoading(true);
            setError(false);
            setReloadKey((key) => key + 1);
          }}
        />
      </div>
    );
  }

  const addProduct = () => {
    hapticFeedback('light');
    navigate('/admin/catalog/create');
  };

  return (
    <div className="flex flex-col gap-4 px-4 pt-2 animate-fade-in">
      <PageHeader
        title={t('ownerMenu_title')}
        subtitle={t('ownerMenu_count', { n: products.length })}
        action={
          <Button icon={Plus} onClick={addProduct}>
            {t('ownerMenu_add')}
          </Button>
        }
      />

      <Button variant="secondary" icon={PackageX} onClick={() => navigate('/admin/stoplist')} fullWidth>
        {t('ownerMenu_stoplist')}
      </Button>

      <div className="relative">
        <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-fg-muted" />
        <input
          type="search"
          aria-label={t('ownerMenu_search')}
          placeholder={t('ownerMenu_search')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className={cx(fieldClass, 'bg-surface pl-10')}
        />
      </div>

      <ul className="flex flex-col gap-3">
        {filteredProducts.map(product => {
          const variants = product.variants ?? [];
          const startingPrice = variants.length > 0
            ? Math.min(...variants.map((v) => toAmount(v.price)))
            : 0;
          const active = product.status === 'active';
          const edit = () => navigate(`/admin/catalog/edit/${product.id}`);

          return (
            <li key={product.id} className="overflow-hidden rounded-card border border-line bg-surface">
              <div className="flex">
                {/* The picture fills the whole left side of the card; without one a neutral placeholder does */}
                <div className={cx('relative w-28 shrink-0 self-stretch bg-surface-2', !active && 'opacity-60')}>
                  <ProductImage src={product.image_url} className="absolute inset-0 size-full" />
                </div>

                <button
                  type="button"
                  onClick={edit}
                  className="flex min-w-0 flex-1 cursor-pointer flex-col gap-1.5 p-3 text-left focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand"
                >
                  <span className="line-clamp-2 text-body font-bold text-fg">{product.name}</span>
                  <span className="flex flex-wrap items-center gap-1.5">
                    <Pill tone="brand">{product.category?.name || t('ownerMenu_noCategory')}</Pill>
                    {!active && <Pill tone="danger">{t('ownerMenu_inactive')}</Pill>}
                    {variants.some((v) => isMeasuredUnit(v.unit as MenuUnit)) && <Pill>{t('ownerMenu_byWeight')}</Pill>}
                  </span>
                  <span className="mt-auto text-footnote text-fg-muted">
                    {/* "25 000 so'mdan": from the cheapest of several variants */}
                    <span className="text-body font-bold text-brand">
                      {variants.length > 1 ? t('ownerMenu_priceFrom', { price: formatMoney(startingPrice) }) : formatMoney(startingPrice)}
                    </span>
                  </span>
                </button>
              </div>

              {/* Actions: on/off, edit, and delete kept apart from edit so one is not tapped for the other */}
              <div className="flex items-center gap-2 border-t border-line pl-3 pr-1">
                <span className="text-footnote font-medium text-fg-muted">{t('ownerMenu_onSale')}</span>
                <Switch checked={active} onChange={() => toggleActive(product.id)} label={t('ownerMenu_onSaleOf', { name: product.name })} />
                <span className="flex-1" />
                <IconButton icon={Pencil} variant="surface" label={t('ownerMenu_editOf', { name: product.name })} onClick={edit} />
                <IconButton icon={Trash2} variant="danger" label={t('ownerMenu_deleteOf', { name: product.name })} onClick={() => deleteProduct(product)} className="ml-4" />
              </div>
            </li>
          );
        })}
      </ul>

      {filteredProducts.length === 0 &&
        (products.length === 0 ? (
          <EmptyState
            icon={PackageOpen}
            title={t('ownerMenu_emptyTitle')}
            text={t('ownerMenu_emptyText')}
            action={
              <Button icon={Plus} onClick={addProduct}>
                {t('ownerMenu_addFirst')}
              </Button>
            }
          />
        ) : (
          <EmptyState icon={SearchX} title={t('ownerMenu_nothingFound')} text={t('ownerMenu_nothingFoundText')} />
        ))}
    </div>
  );
}
