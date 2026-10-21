import React, { useState, useEffect, useRef } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Image as ImageIcon, Plus, Trash2 } from 'lucide-react';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import { useMainButton } from '../hooks/useMainButton';
import { fetchOwnerCategories, fetchOwnerProducts, productFormData, saveProduct, type Category } from '@rizqim/staff';
import { staffApi } from '../services/staffApi';
import { getApiErrorMessage, logApiError } from '../lib/apiError';
import { Button, Card, Field, IconButton, PageHeader, Section, Skeleton, cx, fieldClass } from '../components/ui';
import { Switch } from '../components/staff/Switch';

// A price row of the form. `id` is set for variants that already exist on the server.
interface VariantDraft {
  id: number | null;
  name: string;
  price: string;
}

export default function OwnerProductForm() {
  const { id } = useParams();
  const isEditing = !!id;
  const navigate = useNavigate();

  const [loading, setLoading] = useState(isEditing);
  const [saving, setSaving] = useState(false);
  const [categories, setCategories] = useState<Category[]>([]);

  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [isActive, setIsActive] = useState(true);

  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);

  const fileInputRef = useRef<HTMLInputElement>(null);
  const formRef = useRef<HTMLFormElement>(null);

  const [variants, setVariants] = useState<VariantDraft[]>([
    { id: null, name: '1-chi Porsiya', price: '' }
  ]);

  useEffect(() => {
    const fetchData = async () => {
      try {
        setCategories(await fetchOwnerCategories(staffApi));

        if (isEditing) {
          const products = await fetchOwnerProducts(staffApi);
          const product = products.find((p) => p.id === Number(id));
          if (product) {
            setName(product.name);
            setDescription(product.description || '');
            setCategoryId(product.category_id?.toString() || '');
            setIsActive(product.status === 'active');
            setImagePreview(product.image_url);
            if (product.variants && product.variants.length > 0) {
              setVariants(product.variants.map((v) => ({
                id: v.id,
                name: v.name ?? '',
                price: v.price.toString()
              })));
            }
          }
        }
      } catch (err) {
        logApiError('Failed to load product:', err);
        void showAlert('Ma\'lumotlarni yuklashda xatolik');
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, [id, isEditing]);

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 5 * 1024 * 1024) {
        void showAlert("Rasm hajmi 5MB dan oshmasligi kerak!");
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
      void showAlert("Kamida bitta variant (narx) bo'lishi shart!");
      return;
    }
    hapticFeedback('medium');
    setVariants(variants.filter((_, i) => i !== index));
  };

  const updateVariant = (index: number, field: 'name' | 'price', value: string) => {
    setVariants(variants.map((variant, i) => (i === index ? { ...variant, [field]: value } : variant)));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    hapticFeedback('medium');

    if (!name || !categoryId || variants.some(v => !v.name || !v.price)) {
      void showAlert("Iltimos, nomi, kategoriya va barcha variantlarning narxlarini kiriting!");
      return;
    }

    setSaving(true);
    try {
      const formData = productFormData({ name, description, category_id: categoryId, is_active: isActive, image: imageFile, variants });

      // The API takes multipart on POST for both (an image can go along), so an update is a POST too
      await saveProduct(staffApi, isEditing ? id : null, formData);

      // The catalog list the owner lands on shows the saved product: no popup to dismiss
      notificationOccurred('success');
      navigate('/admin/catalog');
    } catch (err) {
      logApiError('Failed to save product:', err);
      notificationOccurred('error');
      void showAlert(getApiErrorMessage(err) || 'Xatolik yuz berdi');
    } finally {
      setSaving(false);
    }
  };

  // The one action of the screen, always at the bottom. requestSubmit runs the form's own validation first.
  useMainButton({
    text: isEditing ? "O'zgarishlarni saqlash" : "Mahsulotni qo'shish",
    onClick: () => formRef.current?.requestSubmit(),
    progress: saving,
    visible: !loading,
  });

  const title = isEditing ? 'Tahrirlash' : 'Yangi mahsulot';

  if (loading) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2" role="status" aria-label="Yuklanmoqda">
        <PageHeader title={title} back />
        <Skeleton className="mx-auto size-36 rounded-sheet" />
        <Skeleton className="h-64 rounded-card" />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-4 px-4 pt-2 animate-fade-in">
      <PageHeader title={title} back />

      <form ref={formRef} onSubmit={handleSubmit} className="flex flex-col gap-5">
        {/* Image upload */}
        <div className="flex flex-col items-center gap-3">
          <button
            type="button"
            aria-label={imagePreview ? "Rasmni almashtirish" : 'Rasm yuklash'}
            onClick={() => fileInputRef.current?.click()}
            className="relative flex size-36 cursor-pointer flex-col items-center justify-center gap-2 overflow-hidden rounded-sheet border border-line bg-surface-2 text-fg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          >
            {imagePreview ? (
              <img src={imagePreview} alt="" className="absolute inset-0 size-full object-cover" />
            ) : (
              <>
                <ImageIcon aria-hidden="true" className="size-9 text-brand" />
                <span className="text-footnote font-bold">Rasm yuklash</span>
              </>
            )}
          </button>
          <input
            type="file"
            ref={fileInputRef}
            onChange={handleImageChange}
            accept="image/jpeg,image/png,image/webp"
            className="hidden"
            tabIndex={-1}
          />
          {imagePreview && (
            <Button
              variant="secondary"
              icon={Trash2}
              onClick={() => {
                setImageFile(null);
                setImagePreview(null);
              }}
            >
              Rasmni o'chirish
            </Button>
          )}
        </div>

        <Section title="Asosiy ma'lumotlar">
          <Card className="flex flex-col gap-4">
            <div className="flex items-center justify-between gap-3">
              <span className="text-body font-medium text-fg">Sotuvda</span>
              <Switch checked={isActive} onChange={setIsActive} label="Sotuvda" />
            </div>

            <Field label="Kategoriya *">
              {(props) => (
                <select {...props} value={categoryId} onChange={(e) => setCategoryId(e.target.value)} className={fieldClass} required>
                  <option value="" disabled>Tanlang...</option>
                  {categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              )}
            </Field>

            <Field label="Nomi *">
              {(props) => (
                <input {...props} value={name} onChange={(e) => setName(e.target.value)} className={fieldClass} placeholder="Masalan: Achchiq lavash" required />
              )}
            </Field>

            <Field label="Ta'rifi (ixtiyoriy)">
              {(props) => (
                <textarea
                  {...props}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  rows={3}
                  className={cx(fieldClass, 'resize-none')}
                  placeholder="Mahsulot tarkibi va ma'lumotlari..."
                />
              )}
            </Field>
          </Card>
        </Section>

        <Section title="Narx va variantlar">
          <div className="flex flex-col gap-3">
            {variants.map((variant, index) => (
              <Card key={index} className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                  <h3 className="text-body font-bold text-fg">Variant {index + 1}</h3>
                  <IconButton icon={Trash2} variant="danger" label={`Variant ${index + 1}ni o'chirish`} onClick={() => removeVariant(index)} className="-my-1 -mr-2" />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <Field label="Nomi">
                    {(props) => (
                      <input
                        {...props}
                        value={variant.name}
                        onChange={(e) => updateVariant(index, 'name', e.target.value)}
                        placeholder="Mini, Katta, 1 L..."
                        className={fieldClass}
                        required
                      />
                    )}
                  </Field>
                  <Field label="Narxi (so'm)">
                    {(props) => (
                      <input
                        {...props}
                        type="number"
                        inputMode="numeric"
                        value={variant.price}
                        onChange={(e) => updateVariant(index, 'price', e.target.value)}
                        placeholder="0"
                        className={cx(fieldClass, 'font-bold')}
                        required
                      />
                    )}
                  </Field>
                </div>
              </Card>
            ))}

            <Button variant="secondary" icon={Plus} onClick={addVariant} fullWidth>
              Yana variant qo'shish
            </Button>
          </div>
        </Section>
      </form>
    </div>
  );
}
