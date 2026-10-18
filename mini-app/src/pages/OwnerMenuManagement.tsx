import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { PlusCircle, Search, Edit2, Trash2, Tag, CheckCircle2, XCircle } from 'lucide-react';

import { hapticFeedback } from '../lib/telegram';
import api from '../services/api';
import { FullScreenLoader, ErrorFallback } from '../App';

export default function OwnerMenuManagement() {
  const navigate = useNavigate();
  
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  
  const fetchProducts = async () => {
    try {
      setLoading(true);
      const res = await api.get('/owner/products');
      setProducts(res.data.data);
      setError(null);
    } catch (err) {
      console.error(err);
      setError('Mahsulotlarni yuklashda xatolik yuz berdi');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProducts();
  }, []);

  const toggleActive = async (id: number) => {
    hapticFeedback('light');
    // Optimistic update
    setProducts(prev => prev.map(p => p.id === id ? { ...p, status: p.status === 'active' ? 'inactive' : 'active' } : p));
    try {
      await api.post(`/owner/products/${id}?toggle_active=1`);
    } catch (err) {
      // Revert on error
      setProducts(prev => prev.map(p => p.id === id ? { ...p, status: p.status === 'active' ? 'inactive' : 'active' } : p));
      hapticFeedback('error');
    }
  };

  const deleteProduct = async (id: number) => {
    const confirmDelete = window.confirm("Rostdan ham bu mahsulotni o'chirmoqchimisiz?");
    if (!confirmDelete) return;

    hapticFeedback('medium');
    try {
      await api.delete(`/owner/products/${id}`);
      setProducts(prev => prev.filter(p => p.id !== id));
      hapticFeedback('success');
    } catch (err) {
      alert("O'chirishda xatolik yuz berdi");
      hapticFeedback('error');
    }
  };

  const formatMoney = (amount: number) => {
    return new Intl.NumberFormat('uz-UZ', { style: 'currency', currency: 'UZS', maximumFractionDigits: 0 }).format(amount);
  };

  const filteredProducts = products.filter(p => p.name.toLowerCase().includes(search.toLowerCase()));

  if (loading && products.length === 0) return <FullScreenLoader message="Menyu yuklanmoqda..." />;
  if (error) return <ErrorFallback error={error} />;

  return (
    <div className="p-4 pb-24 animate-fade-in min-h-screen bg-gradient-to-br from-bg via-bg to-primary/5">
      <div className="flex justify-between items-center mb-6 pt-2">
        <div className="relative">
          <h1 className="relative text-3xl font-black text-text">
            Katalog
          </h1>
          <p className="text-xs text-hint font-medium mt-1">Jami {products.length} ta mahsulot</p>
        </div>
        <button 
          onClick={() => {
            hapticFeedback('light');
            navigate('/admin/catalog/create');
          }}
          className="flex items-center gap-2 px-5 py-3 bg-primary text-white font-bold rounded-2xl active:scale-95 transition-all"
        >
          <PlusCircle className="w-5 h-5" />
          <span className="text-sm">Qo'shish</span>
        </button>
      </div>

      <div className="relative mb-8 group">
        <div className="relative bg-secondary/80 backdrop-blur-xl border border-white/5 rounded-[24px] p-1 flex items-center shadow-inner">
          <div className="pl-4">
            <Search className="w-5 h-5 text-hint/50" />
          </div>
          <input
            type="text"
            placeholder="Mahsulot qidirish..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full bg-transparent border-none text-text-h text-sm pl-3 pr-4 py-3.5 focus:ring-0 outline-none transition-all placeholder:text-hint/50 font-medium"
          />
        </div>
      </div>

      <div className="space-y-4">
        {filteredProducts.map(product => {
          const startingPrice = product.variants?.length > 0 
            ? Math.min(...product.variants.map((v: any) => v.price))
            : 0;

          return (
            <div 
              key={product.id} 
              className="bg-secondary rounded-2xl overflow-hidden flex border border-white/5 shadow-sm relative group cursor-pointer"
              onClick={() => navigate(`/admin/catalog/edit/${product.id}`)}
            >
              <div className="w-24 h-28 bg-bg/50 shrink-0 overflow-hidden relative">
                {product.image_url ? (
                  <img src={product.image_url} alt={product.name} className="w-full h-full object-cover transform hover:scale-110 transition duration-500" />
                ) : (
                  <div className="w-full h-full flex items-center justify-center text-hint/30">
                    <Tag className="w-8 h-8" />
                  </div>
                )}
                {product.status !== 'active' && (
                  <div className="absolute inset-0 bg-bg/60 backdrop-blur-sm flex items-center justify-center">
                    <span className="text-[10px] font-bold tracking-widest uppercase text-white bg-red-500/90 px-2 py-0.5 rounded-md shadow-sm">Nofaol</span>
                  </div>
                )}
              </div>

                <div className="flex-1 min-w-0 flex flex-col justify-between p-3">
                  <div>
                    <div className="flex justify-between items-start mb-1 gap-2">
                      <h3 className="font-semibold text-sm text-text-h leading-tight line-clamp-2">{product.name}</h3>
                      <button 
                        onClick={(e) => { e.stopPropagation(); toggleActive(product.id); }}
                        className={`shrink-0 p-1.5 rounded-full transition-colors active:scale-90 ${product.status === 'active' ? 'text-green-500 bg-green-500/10 hover:bg-green-500/20' : 'text-hint bg-bg'}`}
                      >
                        {product.status === 'active' ? <CheckCircle2 className="w-4 h-4" /> : <XCircle className="w-4 h-4" />}
                      </button>
                    </div>
                    <p className="text-[10px] text-primary/80 font-medium px-2 py-0.5 bg-primary/10 rounded border border-primary/20 inline-block mb-2">
                      {product.category?.name || 'Kategoriyasiz'}
                    </p>
                  </div>
                  
                  <div className="flex items-end justify-between mt-1">
                    <div className="flex items-center gap-1">
                      <span className="text-[10px] text-hint">dan</span>
                      <p className="font-bold text-sm text-text-h">
                        {formatMoney(startingPrice)}
                      </p>
                    </div>
                    
                    <div className="flex gap-2">
                      <button 
                        onClick={(e) => { e.stopPropagation(); navigate(`/admin/catalog/edit/${product.id}`); }}
                        className="p-2 bg-secondary text-text-h rounded-lg border border-white/5 active:bg-primary active:text-white transition-colors"
                      >
                        <Edit2 className="w-4 h-4" />
                      </button>
                      <button 
                        onClick={(e) => { e.stopPropagation(); deleteProduct(product.id); }}
                        className="p-2 bg-red-500/10 text-red-500 rounded-lg active:bg-red-500 active:text-white transition-colors"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
          );
        })}

        {filteredProducts.length === 0 && (
          <div className="text-center py-20 flex flex-col items-center">
            <div className="w-24 h-24 bg-secondary/50 rounded-[32px] flex items-center justify-center mb-4 border border-white/5 shadow-inner">
              <Tag className="w-10 h-10 text-hint/30" />
            </div>
            <p className="text-text-h font-bold text-lg mb-1">Hech narsa topilmadi</p>
            <p className="text-hint text-sm">Yangi mahsulot qo'shish uchun yuqoridagi tugmani bosing</p>
          </div>
        )}
      </div>
    </div>
  );
}
