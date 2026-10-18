import React, { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useLanguageStore } from '../store/languageStore';
import { hapticFeedback } from '../lib/telegram';
import { ArrowLeft, Search, X, Star, Heart } from 'lucide-react';
import api from '../services/api';
import { logApiError } from '../lib/apiError';

import { useAddressStore } from '../store/useAddressStore';

function formatUzbekSum(value: number | string | undefined) {
  if (value === undefined || value === null) return "0 so'm";
  const numValue = Math.round(Number(value));
  return numValue.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " so'm";
}

export default function Stores() {
  const navigate = useNavigate();
  const { language } = useLanguageStore();
  const [stores, setStores] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [favoriteStores, setFavoriteStores] = useState<string[]>([]);
  const { addresses, selectedAddressId } = useAddressStore();
  const activeAddress = addresses.find(a => a.id === selectedAddressId) || addresses[0];

  useEffect(() => {
    const fetchStores = async () => {
      setLoading(true);
      try {
        const params: any = {};
        if (activeAddress) {
          if (activeAddress.lat) params.lat = activeAddress.lat;
          if (activeAddress.lng) params.lng = activeAddress.lng;
          if (activeAddress.region_id) params.region_id = activeAddress.region_id;
          if (activeAddress.district_id) params.district_id = activeAddress.district_id;
        }

        const res = await api.get('/stores', { params });
        if (res.data.success) {
          setStores(res.data.data || []);
        }
      } catch (err) {
        logApiError('Failed to load stores:', err);
      } finally {
        setLoading(false);
      }
    };
    fetchStores();
  }, [selectedAddressId, addresses]);

  const toggleFavoriteStore = (e: React.MouseEvent, uuid: string) => {
    e.stopPropagation();
    hapticFeedback('medium');
    setFavoriteStores(prev => 
      prev.includes(uuid) ? prev.filter(id => id !== uuid) : [...prev, uuid]
    );
  };

  const filteredStores = useMemo(() => {
    if (!searchQuery) return stores;
    return stores.filter(store => 
      store.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (store.description && store.description.toLowerCase().includes(searchQuery.toLowerCase()))
    );
  }, [stores, searchQuery]);

  return (
    <div className="min-h-screen bg-slate-50 pb-28 animate-fade-in flex flex-col">
      {/* Header */}
      <div className="sticky top-0 bg-white border-b border-slate-100 px-4 py-4 flex items-center justify-between z-20 shadow-sm">
        <div className="flex items-center gap-3">
          <button 
            onClick={() => { hapticFeedback('light'); navigate(-1); }} 
            className="w-10 h-10 rounded-2xl bg-slate-50 border border-slate-100 text-slate-800 flex items-center justify-center active:scale-95 transition"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div>
            <h1 className="text-sm font-black text-slate-800 leading-none">
              {language === 'uz' ? "Barcha oshxonalar" : "Все рестораны"}
            </h1>
            <p className="text-[10px] text-slate-400 font-bold mt-1 uppercase tracking-wider">
              {filteredStores.length} {language === 'uz' ? 'oshxona' : 'ресторанов'}
            </p>
          </div>
        </div>
      </div>

      {/* Search Bar */}
      <div className="bg-white px-4 py-3 border-b border-slate-100">
        <div className="relative">
          <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <Search className="w-4 h-4" />
          </span>
          <input 
            type="text"
            placeholder={language === 'uz' ? "Oshxonalarni qidirish..." : "Поиск ресторанов..."}
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-10 pr-9 py-2 bg-slate-50 border border-slate-100 rounded-xl text-xs placeholder-slate-400 font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500/10 focus:border-amber-500 transition-all text-slate-800"
          />
          {searchQuery && (
            <button 
              onClick={() => setSearchQuery('')}
              className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-650"
            >
              <X className="w-4 h-4" />
            </button>
          )}
        </div>
      </div>

      {/* Stores Grid */}
      <div className="flex-1 p-4">
        {loading ? (
          <div className="flex flex-col items-center justify-center py-24 gap-3">
            <div className="w-8 h-8 border-4 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
            <p className="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Yuklanmoqda...</p>
          </div>
        ) : filteredStores.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-24 px-6 text-center gap-4">
            <div className="w-16 h-16 bg-slate-100 border border-slate-200/50 rounded-full flex items-center justify-center text-slate-400">
              🏪
            </div>
            <div>
              <h3 className="text-sm font-black text-slate-800">
                {language === 'uz' ? 'Oshxonalar topilmadi' : 'Рестораны не найдены'}
              </h3>
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 animate-fade-in">
            {filteredStores.map((store) => {
              const isFav = favoriteStores.includes(store.uuid);
              const hasLogo = store.logo_url && store.logo_url.trim() !== "";
              const initials = store.name.split(' ').map((n: string) => n[0]).join('').slice(0, 2).toUpperCase();

              return (
                <div 
                  key={store.id} 
                  onClick={() => navigate(`/store/${store.uuid}`)}
                  className="bg-white rounded-[28px] border border-slate-100 p-4 shadow-[0_8px_30px_rgb(0,0,0,0.02)] cursor-pointer flex gap-4 hover:shadow-md hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-300 relative group"
                >
                  {/* Favorite Heart Trigger */}
                  <button 
                    onClick={(e) => toggleFavoriteStore(e, store.uuid)}
                    className="absolute top-4 right-4 p-2 bg-slate-50 hover:bg-slate-100 rounded-full shadow-sm z-10 active:scale-75 transition text-slate-350"
                  >
                    <Heart className={`w-4 h-4 ${isFav ? 'fill-rose-500 text-rose-500' : ''}`} />
                  </button>

                  {/* Logo */}
                  {hasLogo ? (
                    <div className="w-20 h-20 bg-slate-50 rounded-2xl overflow-hidden shadow-inner border border-slate-100 shrink-0">
                      <img src={store.logo_url} alt="" className="w-full h-full object-cover" />
                    </div>
                  ) : (
                    <div className="w-20 h-20 bg-amber-100 text-amber-700 rounded-2xl flex items-center justify-center font-black text-lg tracking-tight border border-amber-200 shrink-0">
                      {initials}
                    </div>
                  )}

                  {/* Details */}
                  <div className="flex-1 min-w-0 flex flex-col justify-between">
                    <div>
                      <h3 className="text-sm font-black text-slate-800 truncate leading-snug flex items-center gap-1.5">
                        {store.name}
                        {store.is_premium && <span className="text-[10px]" title="Premium Restoran">👑</span>}
                      </h3>
                      <p className="text-[10px] text-slate-400 font-medium line-clamp-2 mt-1 leading-relaxed">
                        {store.description || "Mazali taomlar"}
                      </p>
                    </div>

                    <div className="flex items-center gap-3.5 mt-2.5 pt-2 border-t border-slate-50 text-[10px] font-bold">
                      <span className="text-amber-500 flex items-center gap-0.5">
                        <Star className="w-3 h-3 fill-amber-500" /> {store.rating || "4.5"}
                      </span>
                      <span className="text-slate-400">
                        {store.delivery_time || "20-30 daqiqa"}
                      </span>
                      <span className="text-slate-400">
                        Min: {formatUzbekSum(store.min_order !== undefined && store.min_order !== null ? store.min_order : 30000)}
                      </span>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
