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

export default function OwnerMenuManagement() {
  const navigate = useNavigate();

  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0); // bumped by \"Qayta urinish\" after a failed load
  const [search, setSearch] = useState('');

  useEffect(() => {
    let cancelled = false;
    const fetchProducts = async () => {
      try {
        const list = await fetchOwnerProducts(staffApi);
        if (cancelled) return;
        setProducts(list);
        setError(null);
      } catch (err) {
        if (cancelled) return;
        logApiError('Failed to load products:', err);
        setError('Mahsulotlarni yuklashda xatolik yuz berdi');
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
    const confirmed = await showConfirm(`"${product.name}" mahsuloti o'chiriladi. Buni qaytarib bo'lmaydi.`, {
      title: "Mahsulotni o'chirish",
      confirmText: "O'chirish",
      destructive: true,
    });
    if (!confirmed) return;

    hapticFeedback('medium');
    try {
      await removeProduct(staffApi, product.id);
      setProducts(prev => prev.filter(p => p.id !== product.id));
      notificationOccurred('success');
    } catch {
      void showAlert("O'chirishda xatolik yuz berdi");
      notificationOccurred('error');
    }
  };


  const filteredProducts = products.filter(p => p.name.toLowerCase().includes(search.toLowerCase()));

  if (loading && products.length === 0) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title="Katalog" />
        <CardSkeletons count={3} lines={3} label="Menyu yuklanmoqda" />
      </div>
    );
  }
  if (error) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title="Katalog" />
        <InlineError
          onRetry={() => {
            setLoading(true);
            setError(null);
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
        title="Katalog"
        subtitle={`Jami ${products.length} ta mahsulot`}
        action={
          <Button icon={Plus} onClick={addProduct}>
            Qo'shish
          </Button>
        }
      />

      <Button variant="secondary" icon={PackageX} onClick={() => navigate('/admin/stoplist')} fullWidth>
        Stop-list va qozon
      </Button>

      <div className="relative">
        <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-fg-muted" />
        <input
          type="search"
          aria-label="Mahsulot qidirish"
          placeholder="Mahsulot qidirish"
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
                    <Pill tone="brand">{product.category?.name || 'Kategoriyasiz'}</Pill>
                    {!active && <Pill tone="danger">Nofaol</Pill>}
                    {variants.some((v) => isMeasuredUnit(v.unit as MenuUnit)) && <Pill>Vazn bo'yicha</Pill>}
                  </span>
                  <span className="mt-auto text-footnote text-fg-muted">
                    {/* "25 000 so'mdan": from the cheapest of several variants */}
                    <span className="text-body font-bold text-brand">{formatMoney(startingPrice)}</span>
                    {variants.length > 1 && 'dan'}
                  </span>
                </button>
              </div>

              {/* Actions: on/off, edit, and delete kept apart from edit so one is not tapped for the other */}
              <div className="flex items-center gap-2 border-t border-line pl-3 pr-1">
                <span className="text-footnote font-medium text-fg-muted">Sotuvda</span>
                <Switch checked={active} onChange={() => toggleActive(product.id)} label={`Sotuvda: ${product.name}`} />
                <span className="flex-1" />
                <IconButton icon={Pencil} variant="surface" label={`${product.name}: tahrirlash`} onClick={edit} />
                <IconButton icon={Trash2} variant="danger" label={`${product.name}: o'chirish`} onClick={() => deleteProduct(product)} className="ml-4" />
              </div>
            </li>
          );
        })}
      </ul>

      {filteredProducts.length === 0 &&
        (products.length === 0 ? (
          <EmptyState
            icon={PackageOpen}
            title="Katalog bo'sh"
            text="Birinchi mahsulotni qo'shing: u mijozlarga shu zahoti ko'rinadi."
            action={
              <Button icon={Plus} onClick={addProduct}>
                Mahsulot qo'shish
              </Button>
            }
          />
        ) : (
          <EmptyState icon={SearchX} title="Hech narsa topilmadi" text="Boshqa so'z bilan qidirib ko'ring." />
        ))}
    </div>
  );
}
