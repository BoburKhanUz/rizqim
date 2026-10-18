import React, { useState, useEffect, useRef } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ChevronLeft, Save, Image as ImageIcon, Plus, Trash2, LayoutGrid, DollarSign } from 'lucide-react';
import { hapticFeedback } from '../lib/telegram';
import api from '../services/api';
import { FullScreenLoader } from '../App';

export default function OwnerProductForm() {
  const { id } = useParams();
  const isEditing = !!id;
  const navigate = useNavigate();
  
  const [loading, setLoading] = useState(isEditing);
  const [saving, setSaving] = useState(false);
  const [categories, setCategories] = useState<any[]>([]);
  
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [isActive, setIsActive] = useState(true);
  
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [variants, setVariants] = useState<any[]>([
    { id: null, name: '1-chi Porsiya', price: '' }
  ]);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const catRes = await api.get('/owner/categories');
        if (catRes.data.success) {
          setCategories(catRes.data.data);
        }

        if (isEditing) {
          const prodRes = await api.get('/owner/products');
          const product = prodRes.data.data.find((p: any) => p.id === Number(id));
          if (product) {
            setName(product.name);
            setDescription(product.description || '');
            setCategoryId(product.category_id?.toString() || '');
            setIsActive(product.status === 'active');
            setImagePreview(product.image_url);
            if (product.variants && product.variants.length > 0) {
              setVariants(product.variants.map((v: any) => ({
                id: v.id,
                name: v.name,
                price: v.price.toString()
              })));
            }
          }
        }
      } catch (err) {
        console.error(err);
        alert('Ma\'lumotlarni yuklashda xatolik');
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, [id]);

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 5 * 1024 * 1024) {
        alert("Rasm hajmi 5MB dan oshmasligi kerak!");
        return;
      }
      setImageFile(file);
      setImagePreview(URL.createObjectURL(file));
    }
  };

  const addVariant = () => {
    hapticFeedback('light');
    setVariants([...variants, { id: null, name: '', price: '' }]);
  };

  const removeVariant = (index: number) => {
    if (variants.length === 1) {
      alert("Kamida bitta variant (narx) bo'lishi shart!");
      return;
    }
    hapticFeedback('medium');
    setVariants(variants.filter((_, i) => i !== index));
  };

  const updateVariant = (index: number, field: string, value: string) => {
    const newVariants = [...variants];
    newVariants[index][field] = value;
    setVariants(newVariants);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    hapticFeedback('medium');
    
    if (!name || !categoryId || variants.some(v => !v.name || !v.price)) {
      alert("Iltimos, nomi, kategoriya va barcha variantlarning narxlarini kiriting!");
      return;
    }

    setSaving(true);
    try {
      const formData = new FormData();
      formData.append('name', name);
      formData.append('description', description);
      formData.append('category_id', categoryId);
      formData.append('is_active', isActive ? '1' : '0');
      
      if (imageFile) {
        formData.append('image', imageFile);
      }
      
      formData.append('variants', JSON.stringify(variants));

      if (isEditing) {
        // Since api.php has Route::post() to handle multipart/form-data, we just send a POST request.
        await api.post(`/owner/products/${id}`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
        alert('Mahsulot muvaffaqiyatli yangilandi!');
      } else {
        await api.post('/owner/products', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
        alert('Mahsulot muvaffaqiyatli qo\'shildi!');
      }
      
      hapticFeedback('success');
      navigate('/admin/catalog');
    } catch (err: any) {
      console.error(err);
      alert(err.response?.data?.message || 'Xatolik yuz berdi');
      hapticFeedback('error');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <FullScreenLoader message="Yuklanmoqda..." />;

  return (
    <div className="p-4 pb-24 animate-fade-in min-h-screen bg-gradient-to-br from-bg via-bg to-primary/5">
      <div className="flex items-center gap-3 mb-8 pt-2">
        <button 
          onClick={() => {
            hapticFeedback('light');
            navigate(-1);
          }}
          className="p-2.5 bg-secondary/80 backdrop-blur-md rounded-full shadow-sm active:scale-90 transition-all border border-white/5"
        >
          <ChevronLeft className="w-6 h-6 text-text" />
        </button>
        <h1 className="text-2xl font-black text-text">
          {isEditing ? 'Tahrirlash' : 'Yangi Mahsulot'}
        </h1>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        
        {/* Image Upload */}
        <div className="flex flex-col items-center mb-2">
          <div className="relative group">
            <div 
              onClick={() => fileInputRef.current?.click()}
              className="w-36 h-36 bg-secondary/80 backdrop-blur-xl rounded-[32px] border border-white/10 flex flex-col items-center justify-center relative overflow-hidden cursor-pointer active:scale-95 transition-transform"
            >
              {imagePreview ? (
                <>
                  <img src={imagePreview} alt="Preview" className="w-full h-full object-cover transform group-hover:scale-110 transition duration-500" />
                  <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <ImageIcon className="w-8 h-8 text-white" />
                  </div>
                </>
              ) : (
                <div className="flex flex-col items-center gap-2">
                  <div className="p-3 bg-primary/10 rounded-full">
                    <ImageIcon className="w-8 h-8 text-primary/70" />
                  </div>
                  <span className="text-[11px] font-extrabold text-hint/70 uppercase tracking-widest">Rasm yuklash</span>
                </div>
              )}
              <input 
                type="file" 
                ref={fileInputRef}
                onChange={handleImageChange}
                accept="image/jpeg,image/png,image/webp"
                className="hidden" 
              />
            </div>
          </div>
          {imagePreview && (
            <button 
              type="button"
              onClick={() => {
                setImageFile(null);
                setImagePreview(null);
              }}
              className="text-xs font-bold text-red-500 mt-4 px-4 py-1.5 bg-red-500/10 rounded-full active:scale-95 transition-transform"
            >
              Rasmni o'chirish
            </button>
          )}
        </div>

        <div className="relative">
          <div className="absolute -inset-0.5 bg-gradient-to-br from-primary/10 to-accent/10 rounded-[28px] blur opacity-50"></div>
          <div className="relative bg-secondary/80 backdrop-blur-xl p-5 rounded-[24px] border border-white/5 space-y-5 shadow-sm">
            <div className="flex items-center justify-between mb-2">
              <div className="flex items-center gap-2 text-primary">
                <div className="p-1.5 bg-primary/10 rounded-lg">
                  <LayoutGrid className="w-5 h-5" />
                </div>
                <h2 className="font-extrabold text-sm uppercase tracking-wide">Asosiy ma'lumotlar</h2>
              </div>
              <label className="flex items-center gap-2.5 cursor-pointer bg-bg/50 px-3 py-1.5 rounded-xl border border-white/5 shadow-inner">
                <span className="text-xs font-bold text-text-h">Sotuvda</span>
                <input 
                  type="checkbox" 
                  checked={isActive} 
                  onChange={(e) => setIsActive(e.target.checked)}
                  className="w-4 h-4 rounded text-primary focus:ring-primary border-white/10 bg-secondary"
                />
              </label>
            </div>
            
            <div className="space-y-4">
              <div className="group">
                <div className="flex justify-between items-center mb-2">
                  <label className="text-[11px] font-bold text-hint uppercase tracking-wider pl-1 group-focus-within:text-primary transition-colors">Kategoriya *</label>
                </div>
                <div className="relative">
                  <select 
                    value={categoryId} 
                    onChange={(e) => setCategoryId(e.target.value)}
                    className="w-full bg-bg/80 backdrop-blur-sm border border-white/5 rounded-2xl px-4 py-3.5 text-sm focus:border-primary/50 focus:bg-secondary outline-none font-semibold shadow-inner appearance-none transition-all"
                    required
                  >
                    <option value="" disabled>Tanlang...</option>
                    {categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </select>
                  <div className="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-hint">
                    <ChevronLeft className="w-4 h-4 -rotate-90" />
                  </div>
                </div>
              </div>

              <div className="group">
                <label className="text-[11px] font-bold text-hint uppercase tracking-wider mb-2 block pl-1 group-focus-within:text-primary transition-colors">Nomi *</label>
                <input 
                  value={name} 
                  onChange={(e) => setName(e.target.value)} 
                  className="w-full bg-bg/80 backdrop-blur-sm border border-white/5 rounded-2xl px-4 py-3.5 text-sm focus:border-primary/50 focus:bg-secondary outline-none font-bold shadow-inner transition-all placeholder:text-hint/40 placeholder:font-medium" 
                  placeholder="Masalan: Achchiq Lavash" 
                  required
                />
              </div>

              <div className="group">
                <label className="text-[11px] font-bold text-hint uppercase tracking-wider mb-2 block pl-1 group-focus-within:text-primary transition-colors">Ta'rifi (Ixtiyoriy)</label>
                <textarea 
                  value={description} 
                  onChange={(e) => setDescription(e.target.value)} 
                  rows={3}
                  className="w-full bg-bg/80 backdrop-blur-sm border border-white/5 rounded-2xl px-4 py-3.5 text-sm focus:border-primary/50 focus:bg-secondary outline-none font-medium resize-none shadow-inner transition-all placeholder:text-hint/40" 
                  placeholder="Mahsulot tarkibi va ma'lumotlari..." 
                />
              </div>
            </div>
          </div>
        </div>

        <div className="relative">
          <div className="absolute -inset-0.5 bg-gradient-to-br from-green-500/10 to-emerald-500/10 rounded-[28px] blur opacity-50"></div>
          <div className="relative bg-secondary/80 backdrop-blur-xl p-5 rounded-[24px] border border-white/5 space-y-5 shadow-sm">
            <div className="flex items-center gap-2 text-green-500 mb-2">
              <div className="p-1.5 bg-green-500/10 rounded-lg">
                <DollarSign className="w-5 h-5" />
              </div>
              <h2 className="font-extrabold text-sm uppercase tracking-wide">Narx va Variantlar</h2>
            </div>
            
            <div className="space-y-3">
              {variants.map((variant, index) => (
                <div key={index} className="flex items-center gap-3 bg-bg/80 backdrop-blur-sm p-3 pl-4 rounded-2xl border border-white/5 shadow-inner relative group/variant">
                  <div className="flex-1 space-y-3">
                    <div className="flex items-center gap-3 border-b border-white/5 pb-3">
                      <span className="text-[10px] font-bold text-hint/70 uppercase tracking-widest w-12">Nomi</span>
                      <input 
                        value={variant.name}
                        onChange={(e) => updateVariant(index, 'name', e.target.value)}
                        placeholder="Mini, Katta, 1 L..."
                        className="bg-transparent border-none text-sm font-bold text-text-h w-full outline-none focus:ring-0 p-0 placeholder:text-hint/30"
                        required
                      />
                    </div>
                    <div className="flex items-center gap-3 pt-1">
                      <span className="text-[10px] font-bold text-hint/70 uppercase tracking-widest w-12">Narxi</span>
                      <input 
                        type="number"
                        value={variant.price}
                        onChange={(e) => updateVariant(index, 'price', e.target.value)}
                        placeholder="0"
                        className="bg-transparent border-none text-base font-black text-green-500 w-full outline-none focus:ring-0 p-0 placeholder:text-green-500/30"
                        required
                      />
                      <span className="text-[10px] font-extrabold text-hint/50 bg-white/5 px-2 py-1 rounded-md">UZS</span>
                    </div>
                  </div>
                  <button 
                    type="button"
                    onClick={() => removeVariant(index)}
                    className="w-12 h-12 flex items-center justify-center shrink-0 bg-red-500/10 text-red-500 rounded-xl active:scale-90 transition-transform hover:bg-red-500 hover:text-white"
                  >
                    <Trash2 className="w-5 h-5" />
                  </button>
                </div>
              ))}
            </div>

            <button 
              type="button"
              onClick={addVariant}
              className="w-full py-4 bg-primary/5 border border-primary/20 border-dashed rounded-2xl flex items-center justify-center gap-2 text-primary font-bold text-sm active:bg-primary/10 transition-colors"
            >
              <Plus className="w-5 h-5" />
              Yana variant qo'shish
            </button>
          </div>
        </div>

        <button 
          type="submit" 
          disabled={saving}
          className="w-full bg-primary text-white font-black text-base py-4 rounded-2xl active:scale-[0.98] transition-all flex items-center justify-center gap-2 mt-6 relative overflow-hidden"
        >
          {saving && <div className="absolute inset-0 bg-black/10"></div>}
          {saving ? (
             <span className="w-6 h-6 border-3 border-white/30 border-t-white rounded-full animate-spin"></span>
          ) : (
            <>
              <Save className="w-5 h-5" />
              <span>{isEditing ? "O'zgarishlarni Saqlash" : "Mahsulotni Qo'shish"}</span>
            </>
          )}
        </button>

      </form>
    </div>
  );
}
