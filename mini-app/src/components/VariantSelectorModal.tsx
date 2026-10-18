import { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { X, Check } from 'lucide-react';
import { hapticFeedback } from '../lib/telegram';

interface ProductVariant {
  id: number;
  uuid: string;
  name: string;
  price: string | number;
  stock: number;
  is_active: boolean;
}

interface VariantSelectorModalProps {
  isOpen: boolean;
  onClose: () => void;
  product: any;
  onAdd: (variant: ProductVariant) => void;
}

function formatUzbekSum(value: number | string | undefined) {
  if (value === undefined || value === null) return "0 so'm";
  const numValue = Math.round(Number(value));
  return numValue.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " so'm";
}

export default function VariantSelectorModal({ isOpen, onClose, product, onAdd }: VariantSelectorModalProps) {
  const [selectedVariant, setSelectedVariant] = useState<ProductVariant | null>(null);

  // Auto-select the first active variant on product change
  useEffect(() => {
    if (product && product.variants && product.variants.length > 0) {
      const firstActive = product.variants.find((v: ProductVariant) => (v.is_active as any) !== false && (v.is_active as any) !== 0 && v.stock > 0);
      setSelectedVariant(firstActive || product.variants[0]);
    } else {
      setSelectedVariant(null);
    }
  }, [product]);

  if (!isOpen || !product) return null;

  const handleSelect = (variant: ProductVariant) => {
    if (!variant.is_active) return;
    hapticFeedback('light');
    setSelectedVariant(variant);
  };

  const handleConfirm = () => {
    if (!selectedVariant) return;
    hapticFeedback('success');
    onAdd(selectedVariant);
    onClose();
  };

  return createPortal(
    <div className="fixed inset-0 z-[100] flex items-end justify-center bg-black/50 backdrop-blur-sm animate-fade-in">
      {/* Backdrop Click */}
      <div className="absolute inset-0" onClick={onClose}></div>

      {/* Sheet Container */}
      <div 
        onClick={(e) => e.stopPropagation()}
        className="relative w-full max-w-md bg-white rounded-t-[32px] p-6 shadow-2xl z-10 animate-slide-up border-t border-slate-100 flex flex-col max-h-[85vh] overflow-hidden pb-safe"
      >
        {/* Header indicator line */}
        <div className="w-12 h-1 bg-slate-200 rounded-full mx-auto mb-4 shrink-0"></div>

        {/* Title Block */}
        <div className="flex justify-between items-start gap-4 mb-4 shrink-0">
          <div className="min-w-0">
            <h3 className="text-base font-black text-slate-800 leading-tight">{product.name}</h3>
            <p className="text-[11px] text-slate-400 font-bold mt-1 uppercase tracking-wider">Tanlash majburiy</p>
          </div>
          <button 
            onClick={onClose}
            className="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200 active:scale-90 transition shrink-0"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Product preview / Details */}
        <div className="flex gap-4 p-3 bg-slate-50 rounded-2xl border border-slate-100 mb-4 shrink-0">
          {product.image_url ? (
            <img src={product.image_url} alt="" className="w-16 h-16 rounded-xl object-cover border border-slate-200 bg-white" />
          ) : (
            <div className="w-16 h-16 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-2xl">🍔</div>
          )}
          <div className="flex-1 min-w-0">
            <p className="text-xs text-slate-500 font-semibold line-clamp-2 leading-relaxed">{product.description || "Mazali taom variantini tanlang"}</p>
            <p className="text-xs font-black text-slate-800 mt-2">
              Asosiy narx: {formatUzbekSum(
                product.price && parseFloat(product.price) > 0 
                  ? parseFloat(product.price)
                  : (product.variants && product.variants.length > 0 
                      ? Math.min(...product.variants.map((v: any) => parseFloat(v.price) || 0)) 
                      : 0)
              )}
            </p>
          </div>
        </div>

        {/* Scrollable Variants List */}
        <div className="flex-1 overflow-y-auto no-scrollbar py-1 space-y-2.5">
          {product.variants?.map((v: ProductVariant) => {
            const isSelected = selectedVariant?.uuid === v.uuid;
            const isOutOfStock = v.stock <= 0;
            const isDisabled = ((v.is_active as any) === false || (v.is_active as any) === 0) || isOutOfStock;

            return (
              <div
                key={v.id}
                onClick={() => !isDisabled && handleSelect(v)}
                className={`p-4 rounded-2xl border-2 flex items-center justify-between transition cursor-pointer ${
                  isDisabled 
                    ? 'border-slate-100 bg-slate-50/50 opacity-50 cursor-not-allowed'
                    : isSelected
                      ? 'border-amber-500 bg-amber-50/20 text-slate-850'
                      : 'border-slate-100 bg-white text-slate-600 hover:bg-slate-50'
                }`}
              >
                <div className="flex items-center gap-3">
                  {/* Custom Radio Button */}
                  <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center transition ${
                    isDisabled 
                      ? 'border-slate-200 bg-slate-100'
                      : isSelected 
                        ? 'border-amber-500 bg-amber-500 text-white' 
                        : 'border-slate-300 bg-white'
                  }`}>
                    {isSelected && <Check className="w-3 h-3 stroke-[3]" />}
                  </div>
                  <div>
                    <p className="text-xs font-black leading-tight text-slate-800">
                      {v.name}
                    </p>
                    {isOutOfStock && (
                      <p className="text-[9px] text-red-500 font-extrabold mt-0.5">Tugagan</p>
                    )}
                  </div>
                </div>
                <span className="text-xs font-black text-slate-800">
                  {formatUzbekSum(v.price)}
                </span>
              </div>
            );
          })}
        </div>

        {/* Footer CTA */}
        <div className="pt-4 border-t border-slate-100 mt-4 shrink-0">
          <button
            onClick={handleConfirm}
            disabled={!selectedVariant}
            className={`w-full py-4 rounded-2xl font-black text-xs text-center text-white transition-all active:scale-[0.98] ${
              selectedVariant 
                ? 'bg-amber-500 hover:bg-amber-600 shadow-lg shadow-amber-500/20 cursor-pointer' 
                : 'bg-slate-300 cursor-not-allowed shadow-none'
            }`}
          >
            {selectedVariant 
              ? `Tasdiqlash — ${formatUzbekSum(selectedVariant.price)}`
              : "Variant tanlang"
            }
          </button>
        </div>
      </div>
    </div>,
    document.body
  );
}
