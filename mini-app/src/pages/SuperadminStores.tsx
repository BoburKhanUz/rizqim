import { useState, useEffect } from 'react';
import { Store, CheckCircle2, XCircle, Search, AlertTriangle, ExternalLink } from 'lucide-react';
import { hapticFeedback } from '../lib/telegram';
import api from '../services/api';
import { logApiError } from '../lib/apiError';
import { FullScreenLoader, ErrorFallback } from '../components/Feedback';
import type { ApiResponse, SuperadminStore } from '../types';

export default function SuperadminStores() {
  const [stores, setStores] = useState<SuperadminStore[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
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
  }, []);

  const copyToClipboard = (text: string) => {
    hapticFeedback('medium');
    navigator.clipboard.writeText(text);
    alert('Link nusxalandi! Endi ownerga yuborishingiz mumkin.');
  };

  const filteredStores = stores.filter(store => 
    store.name.toLowerCase().includes(search.toLowerCase())
  );

  if (loading && stores.length === 0) return <FullScreenLoader message="Restoranlar ro'yxati yuklanmoqda..." />;
  if (error) return <ErrorFallback error={error} />;

  return (
    <div className="p-4 pb-24 animate-fade-in">
      <div className="flex justify-between items-center mb-4">
        <div>
          <h1 className="text-2xl font-black text-text-h">Restoranlar</h1>
          <p className="text-xs text-hint font-medium">Barcha do'konlar ro'yxati ({stores.length})</p>
        </div>
      </div>

      <div className="relative mb-6">
        <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
          <Search className="w-5 h-5 text-hint/50" />
        </div>
        <input
          type="text"
          placeholder="Restoran qidirish..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full bg-secondary border border-hint/10 text-text-h text-sm rounded-2xl pl-11 pr-4 py-3.5 focus:border-primary/50 outline-none transition-all placeholder:text-hint/50 font-medium"
        />
      </div>

      <div className="space-y-3">
        {filteredStores.map(store => {
          const owner = store.owner;
          const inviteLink = owner?.invite_link;
          return (
            <div key={store.id} className="bg-secondary rounded-[24px] p-4 border border-hint/10 shadow-sm flex flex-col gap-4 relative overflow-hidden group">
            
              <div className="flex items-center gap-4">
                <div className="w-14 h-14 bg-bg rounded-[18px] border border-hint/10 flex items-center justify-center shrink-0 shadow-inner">
                  <Store className="w-6 h-6 text-primary" />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <h3 className="font-bold text-text-h truncate">{store.name}</h3>
                    {store.is_active ? (
                      <CheckCircle2 className="w-4 h-4 text-green-500 shrink-0" />
                    ) : (
                      <XCircle className="w-4 h-4 text-red-500 shrink-0" />
                    )}
                  </div>
                  <div className="flex items-center gap-3 text-xs text-hint">
                    <span className="bg-bg px-2 py-0.5 rounded-md border border-hint/10 font-bold text-text">
                      {store.commission_rate}% komissiya
                    </span>
                    <span>{store.branches_count} filial</span>
                  </div>
                </div>
              </div>

              <div className="bg-bg rounded-[16px] p-3 border border-hint/10">
                <p className="text-[10px] uppercase font-bold text-hint mb-2">Menejer (Owner) holati</p>
                {owner ? (
                  <div className="flex items-center justify-between">
                    <div className="flex flex-col">
                      <span className="text-sm font-bold text-text-h">{owner.name}</span>
                      <span className="text-xs text-hint">{owner.phone}</span>
                    </div>
                  
                    {owner.has_joined ? (
                      <div className="flex items-center gap-1.5 bg-green-500/10 text-green-500 px-2 py-1 rounded-lg">
                        <CheckCircle2 className="w-3.5 h-3.5" />
                        <span className="text-[10px] font-bold">Faol</span>
                      </div>
                    ) : (
                      inviteLink ? (
                        <button 
                          onClick={() => copyToClipboard(inviteLink)}
                          className="flex items-center gap-1.5 bg-primary/10 text-primary hover:bg-primary hover:text-white transition-colors px-3 py-1.5 rounded-xl border border-primary/20 active:scale-95"
                        >
                          <ExternalLink className="w-3.5 h-3.5" />
                          <span className="text-[11px] font-bold">Nusxalash</span>
                        </button>
                      ) : (
                        <span className="text-xs text-orange-500 font-medium">Link yo'q</span>
                      )
                    )}
                  </div>
                ) : (
                  <div className="flex items-center gap-2 text-orange-500">
                    <AlertTriangle className="w-4 h-4" />
                    <span className="text-xs font-bold">Owner tayinlanmagan</span>
                  </div>
                )}
              </div>

            </div>
          );
        })}

        {filteredStores.length === 0 && (
          <div className="text-center py-12 text-hint">
            <Store className="w-12 h-12 mx-auto mb-3 opacity-20" />
            <p>Hech narsa topilmadi</p>
          </div>
        )}
      </div>
    </div>
  );
}
