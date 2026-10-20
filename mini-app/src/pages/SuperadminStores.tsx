import { useState, useEffect } from 'react';
import { Store, CheckCircle2, XCircle, Search, AlertTriangle, Copy } from 'lucide-react';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import api from '../services/api';
import { logApiError } from '../lib/apiError';
import { Button, EmptyState, InlineError, PageHeader } from '../components/ui';
import { CardSkeletons } from '../components/staff/Skeleton';
import { Pill } from '../components/staff/Pill';
import { fieldClass } from '../components/staff/fieldStyles';
import type { ApiResponse, SuperadminStore } from '../types';

export default function SuperadminStores() {
  const [stores, setStores] = useState<SuperadminStore[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0); // bumped by \"Qayta urinish\" after a failed load
  const [search, setSearch] = useState('');

  useEffect(() => {
    let cancelled = false;
    const fetchStores = async () => {
      try {
        const res = await api.get<ApiResponse<SuperadminStore[]>>('/superadmin/stores');
        if (cancelled) return;
        setStores(res.data.data);
        setError(null);
      } catch (err) {
        if (cancelled) return;
        logApiError('Failed to load stores:', err);
        setError('Restoranlarni yuklashda xatolik yuz berdi');
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    fetchStores();
    return () => {
      cancelled = true;
    };
  }, [reloadKey]);

  const copyToClipboard = async (text: string) => {
    hapticFeedback('medium');
    try {
      await navigator.clipboard.writeText(text);
    } catch {
      notificationOccurred('error');
      void showAlert("Linkni nusxalab bo'lmadi. Qaytadan urinib ko'ring.");
      return;
    }
    notificationOccurred('success');
    void showAlert('Link nusxalandi! Endi ownerga yuborishingiz mumkin.');
  };

  const filteredStores = stores.filter(store =>
    store.name.toLowerCase().includes(search.toLowerCase())
  );

  if (loading && stores.length === 0) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title="Restoranlar" />
        <CardSkeletons count={3} lines={3} label="Restoranlar ro'yxati yuklanmoqda" />
      </div>
    );
  }
  if (error) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        <PageHeader title="Restoranlar" />
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

  return (
    <div className="flex flex-col gap-4 px-4 pt-2 animate-fade-in">
      <PageHeader title="Restoranlar" subtitle={`Barcha do'konlar ro'yxati (${stores.length})`} />

      <div className="relative">
        <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-fg-muted" />
        <input
          type="search"
          aria-label="Restoran qidirish"
          placeholder="Restoran qidirish..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className={`${fieldClass} bg-surface pl-10`}
        />
      </div>

      <ul className="flex flex-col gap-3">
        {filteredStores.map(store => {
          const owner = store.owner;
          const inviteLink = owner?.invite_link;
          return (
            <li key={store.id} className="flex flex-col gap-3 rounded-card border border-line bg-surface p-3">
              <div className="flex items-center gap-3">
                <span className="flex size-12 shrink-0 items-center justify-center rounded-control bg-brand-soft text-brand-soft-fg">
                  <Store aria-hidden="true" className="size-6" />
                </span>
                <div className="min-w-0 flex-1">
                  <h2 className="truncate text-body font-bold text-fg">{store.name}</h2>
                  <p className="text-footnote text-fg-muted">
                    {store.commission_rate}% komissiya · {store.branches_count} filial
                  </p>
                </div>
                {store.is_active ? (
                  <Pill tone="success" icon={CheckCircle2}>Faol</Pill>
                ) : (
                  <Pill tone="danger" icon={XCircle}>Nofaol</Pill>
                )}
              </div>

              <div className="flex flex-col gap-2 rounded-control bg-surface-2 p-3">
                <p className="text-footnote font-bold text-fg-muted">Restoran egasi</p>
                {owner ? (
                  <div className="flex items-center justify-between gap-3">
                    <div className="flex min-w-0 flex-col">
                      <span className="truncate text-body font-bold text-fg">{owner.name}</span>
                      <span className="text-footnote text-fg-muted">{owner.phone}</span>
                    </div>

                    {owner.has_joined ? (
                      <Pill tone="success" icon={CheckCircle2}>Faol</Pill>
                    ) : inviteLink ? (
                      <Button variant="secondary" icon={Copy} onClick={() => copyToClipboard(inviteLink)} aria-label={`${store.name}: taklif linkini nusxalash`}>
                        Nusxalash
                      </Button>
                    ) : (
                      <Pill tone="warning">Link yo'q</Pill>
                    )}
                  </div>
                ) : (
                  <p className="flex items-center gap-2 text-footnote font-bold text-warning-soft-fg">
                    <AlertTriangle aria-hidden="true" className="size-4 shrink-0" />
                    Egasi tayinlanmagan
                  </p>
                )}
              </div>
            </li>
          );
        })}
      </ul>

      {filteredStores.length === 0 && (
        <EmptyState icon={Store} title="Hech narsa topilmadi" text={search ? "Boshqa nom bilan qidirib ko'ring." : "Hozircha restoranlar yo'q."} />
      )}
    </div>
  );
}
