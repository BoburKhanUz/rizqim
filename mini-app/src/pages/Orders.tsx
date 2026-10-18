import { useState } from 'react';
import { useOrders } from '../hooks/useOrders';
import { useLanguageStore } from '../store/languageStore';
import { hapticFeedback } from '../lib/telegram';
import { Link } from 'react-router-dom';
import api from '../services/api';
import {
  ArrowLeft,
  Clock,
  CheckCircle2,
  Utensils,
  Bike,
  CheckCircle,
  XCircle,
  ChevronDown,
  ChevronUp,
  MapPin,
  CreditCard,
  MessageSquare,
  PackageOpen
} from 'lucide-react';

// Premium Inline Skeleton Loader for modern premium feel
const OrdersSkeleton = () => (
  <div className="flex flex-col gap-3.5 animate-pulse">
    {[1, 2, 3].map(n => (
      <div key={n} className="bg-white rounded-3xl p-4 border border-slate-100 flex flex-col gap-3">
        <div className="flex justify-between items-center">
          <div className="flex flex-col gap-2 w-1/2">
            <div className="h-3 bg-slate-100 rounded-md w-16"></div>
            <div className="h-4 bg-slate-100 rounded-md w-28"></div>
            <div className="h-3 bg-slate-100 rounded-md w-20"></div>
          </div>
          <div className="flex flex-col items-end gap-2 w-1/3">
            <div className="h-6 bg-slate-100 rounded-full w-20"></div>
            <div className="h-4 bg-slate-100 rounded-md w-16"></div>
          </div>
        </div>
      </div>
    ))}
  </div>
);

export default function Orders() {
  const { data: orders = [], isLoading, error } = useOrders();
  const { t, language } = useLanguageStore();
  const [expandedOrderId, setExpandedOrderId] = useState<number | null>(null);

  // Rating States
  const [showRatingModal, setShowRatingModal] = useState(false);
  const [ratingStoreId, setRatingStoreId] = useState<number | null>(null);
  const [ratingOrderId, setRatingOrderId] = useState<number | null>(null);
  const [selectedRating, setSelectedRating] = useState(5);
  const [ratingComment, setRatingComment] = useState("");
  const [isSubmittingRating, setIsSubmittingRating] = useState(false);

  const handleOpenRatingModal = (orderId: number, storeId: number) => {
    hapticFeedback('medium');
    setRatingOrderId(orderId);
    setRatingStoreId(storeId);
    setSelectedRating(5);
    setRatingComment("");
    setShowRatingModal(true);
  };

  const handleSubmitRating = async () => {
    if (!ratingStoreId) return;
    hapticFeedback('medium');
    setIsSubmittingRating(true);
    try {
      await api.post(`/stores/${ratingStoreId}/rate`, {
        rating: selectedRating,
        comment: ratingComment,
        order_id: ratingOrderId,
      });
      hapticFeedback('success');
      alert(language === 'uz' ? "Baho yuborildi! Rahmat." : "Baho yuborildi! Rahmat.");
      setShowRatingModal(false);
    } catch (err: any) {
      console.error(err);
      hapticFeedback('error');
      alert(err.response?.data?.message || "Xatolik yuz berdi");
    } finally {
      setIsSubmittingRating(false);
    }
  };

  const toggleExpand = (orderId: number) => {
    hapticFeedback('light');
    setExpandedOrderId(prev => (prev === orderId ? null : orderId));
  };

  const formatDate = (dateStr: string) => {
    const d = new Date(dateStr);
    return d.toLocaleString(language === 'uz' ? 'uz-UZ' : language === 'ru' ? 'ru-RU' : 'en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const statusConfig = {
    new: {
      bg: 'bg-amber-500/10 text-amber-500 border border-amber-500/20',
      labelKey: 'status_new' as const,
      icon: <Clock className="w-3.5 h-3.5" />,
    },
    accepted: {
      bg: 'bg-blue-500/10 text-blue-500 border border-blue-500/20',
      labelKey: 'status_accepted' as const,
      icon: <CheckCircle2 className="w-3.5 h-3.5" />,
    },
    preparing: {
      bg: 'bg-purple-500/10 text-purple-500 border border-purple-500/20 animate-pulse',
      labelKey: 'status_preparing' as const,
      icon: <Utensils className="w-3.5 h-3.5" />,
    },
    delivery: {
      bg: 'bg-orange-500/10 text-orange-500 border border-orange-500/20',
      labelKey: 'status_delivery' as const,
      icon: <Bike className="w-3.5 h-3.5" />,
    },
    delivered: {
      bg: 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20',
      labelKey: 'status_delivered' as const,
      icon: <CheckCircle className="w-3.5 h-3.5" />,
    },
    canceled: {
      bg: 'bg-red-500/10 text-red-500 border border-red-500/20',
      labelKey: 'status_canceled' as const,
      icon: <XCircle className="w-3.5 h-3.5" />,
    },
  };

  return (
    <div className="p-4 flex flex-col gap-6 min-h-screen pb-28 bg-slate-50">
      {/* Header */}
      <header className="flex items-center gap-4 pb-2 border-b border-slate-100">
        <Link
          to="/"
          onClick={() => hapticFeedback('light')}
          className="p-2 rounded-xl bg-white border border-slate-200/60 shadow-sm active:scale-90 transition-transform"
        >
          <ArrowLeft className="w-5 h-5 text-slate-800" />
        </Link>
        <div>
          <h1 className="text-xl font-bold text-slate-800">{t('myOrders')}</h1>
          <span className="text-[10px] text-slate-400 font-medium">Buyurtmalar tarixi va holati</span>
        </div>
      </header>

      {error ? (
        <div className="bg-red-500/10 border border-red-500/20 p-4 rounded-2xl text-center text-red-500 text-xs font-bold">
          {language === 'uz' ? 'Xatolik yuz berdi. Iltimos qayta urinib ko\'ring.' : 'Error loading history. Please try again.'}
        </div>
      ) : (isLoading && orders.length === 0) ? (
        /* Butter-smooth Skeleton UI replaces harsh FullScreenLoader */
        <OrdersSkeleton />
      ) : orders.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
          <div className="p-4 rounded-full bg-amber-500/10 text-amber-500">
            <PackageOpen className="w-12 h-12" />
          </div>
          <h2 className="text-base font-extrabold text-slate-800">
            {language === 'uz' ? 'Buyurtmalar yo\'q' : language === 'ru' ? 'Заказов пока нет' : 'No orders yet'}
          </h2>
          <p className="text-slate-400 text-xs max-w-xs mb-2 leading-relaxed">
            {language === 'uz'
              ? 'Hali hech qanday buyurtma bermadingiz. Mazali taomlarimizni tatib ko\'ring!'
              : language === 'ru'
                ? 'Вы еще не сделали ни одного заказа. Самое время заказать вкусные блюда!'
                : 'You haven\'t made any orders yet. Time to order delicious food!'}
          </p>
          <Link
            to="/"
            onClick={() => hapticFeedback('light')}
            className="bg-amber-500 hover:bg-amber-600 text-white py-3 px-6 rounded-2xl font-bold text-xs active:scale-95 transition-transform shadow-lg shadow-amber-500/20"
          >
            {language === 'uz' ? 'Katalogga qaytish' : language === 'ru' ? 'В каталог' : 'Go to Catalog'}
          </Link>
        </div>
      ) : (
        <div className="flex flex-col gap-3.5">
          {orders.map(order => {
            const config = statusConfig[order.status] || statusConfig.new;
            const isExpanded = expandedOrderId === order.id;

            return (
              <div
                key={order.id}
                className="bg-white rounded-3xl p-4 border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] transition-all duration-300"
              >
                {/* Header Summary */}
                <div
                  onClick={() => toggleExpand(order.id)}
                  className="flex justify-between items-center cursor-pointer"
                >
                  <div className="flex flex-col gap-1">
                    <span className="text-[10px] font-black text-slate-400">#{order.uuid.substring(0, 8).toUpperCase()}</span>
                    <span className="font-extrabold text-xs text-slate-800">{formatDate(order.created_at)}</span>
                    <span className="text-[10px] text-slate-400 uppercase font-bold flex items-center gap-1">
                      {order.type === 'delivery' ? '🛵 ' + t('delivery') : order.type === 'pickup' ? '🛍️ ' + t('pickup') : '🍽️ Dine-in'}
                    </span>
                  </div>

                  <div className="flex flex-col items-end gap-2.5">
                    <span className={`text-[9px] px-3 py-1 rounded-full font-black flex items-center gap-1.5 ${config.bg}`}>
                      {config.icon}
                      <span>{t(config.labelKey)}</span>
                    </span>
                    <span className="font-black text-xs text-amber-500">{Number(order.total_price).toLocaleString()} UZS</span>
                  </div>
                </div>

                {/* Expanded Details */}
                {isExpanded && (
                  <div className="mt-4 pt-4 border-t border-slate-100 flex flex-col gap-3.5 animate-fade-in">
                    <h4 className="text-[10px] font-black text-slate-400 uppercase tracking-wider">
                      {language === 'uz' ? 'Tafsilotlar' : language === 'ru' ? 'Детали' : 'Details'}
                    </h4>

                    {/* Order Items */}
                    <div className="flex flex-col gap-2.5 bg-slate-50/50 p-3 rounded-2xl border border-slate-100">
                      {order.items?.map((item, idx) => (
                        <div key={idx} className="flex justify-between items-center text-xs">
                          <div className="flex flex-col">
                            <span className="font-bold text-slate-800">
                              {item.product_name || 'Burger'}
                            </span>
                            {item.variant_name && (
                              <span className="text-[10px] text-slate-400 font-medium">({item.variant_name})</span>
                            )}
                          </div>
                          <span className="text-slate-400 text-[10px] font-extrabold">x{item.quantity}</span>
                          <span className="font-extrabold text-slate-800">
                            {Number(item.total_price || (Number(item.unit_price) * item.quantity)).toLocaleString()} UZS
                          </span>
                        </div>
                      ))}
                    </div>

                    {/* Delivery Address */}
                    {order.address && (
                      <div className="text-xs bg-slate-50/50 p-3.5 rounded-2xl border border-slate-100 flex gap-2.5 items-start">
                        <MapPin className="w-4 h-4 text-amber-500 shrink-0 mt-0.5" />
                        <div>
                          <span className="font-black text-slate-400 text-[10px] block mb-0.5">{t('deliveryAddress')}:</span>
                          <span className="text-slate-800 font-bold leading-relaxed">{order.address}</span>
                        </div>
                      </div>
                    )}

                    {/* Payment Info */}
                    <div className="flex justify-between items-center text-xs px-1 text-slate-400 font-bold">
                      <span className="flex items-center gap-1.5">
                        <CreditCard className="w-3.5 h-3.5 text-amber-500" />
                        <span>{language === 'uz' ? 'To\'lov turi' : 'Способ оплаты'}:</span>
                      </span>
                      <span className="font-black text-slate-800 uppercase">{order.payment_method}</span>
                    </div>

                    <div className="flex justify-between items-center text-xs px-1 text-slate-400 font-bold">
                      <span className="flex items-center gap-1.5">
                        <CheckCircle className="w-3.5 h-3.5 text-amber-500" />
                        <span>{language === 'uz' ? 'To\'lov holati' : 'Статус оплаты'}:</span>
                      </span>
                      <span className={`font-black uppercase ${order.payment_status === 'paid' ? 'text-emerald-500' : 'text-amber-500'}`}>
                        {order.payment_status}
                      </span>
                    </div>

                    {/* Cook Notes */}
                    {order.notes && (
                      <div className="text-xs text-slate-400 italic bg-amber-500/5 p-3 rounded-2xl border border-amber-500/10 flex gap-2 items-start font-medium leading-relaxed">
                        <MessageSquare className="w-3.5 h-3.5 text-amber-500 shrink-0 mt-0.5" />
                        <span>💬 "{order.notes}"</span>
                      </div>
                    )}

                    {/* Buttons: Complaint & Rating */}
                    <div className="flex justify-between items-center pt-2 border-t border-slate-100">
                      <Link 
                        to={`/complaints?orderId=${order.id}`}
                        state={{ orderId: order.id }}
                        onClick={() => hapticFeedback('light')}
                        className="text-[10px] font-black text-rose-500 bg-rose-50 px-3.5 py-2 rounded-xl border border-rose-100 active:scale-95 transition"
                      >
                        ⚠️ {language === 'uz' ? 'Shikoyat qilish' : 'Подать жалобу'}
                      </Link>

                      {order.status === 'delivered' && (
                        <button 
                          onClick={() => handleOpenRatingModal(order.id, order.store_id)}
                          className="text-[10px] font-black text-amber-600 bg-amber-50 px-3.5 py-2 rounded-xl border border-amber-100 active:scale-95 transition"
                        >
                          ⭐ {language === 'uz' ? 'Baholash' : 'Оценить'}
                        </button>
                      )}
                    </div>
                  </div>
                )}

                {/* Expand Chevron trigger */}
                <div
                  onClick={() => toggleExpand(order.id)}
                  className="flex justify-center mt-2.5 border-t border-slate-100 pt-1.5 cursor-pointer text-slate-400 hover:text-slate-800 transition-colors"
                >
                  {isExpanded ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                </div>
              </div>
            );
          })}
        </div>
      )}
      {/* Rating Modal */}
      {showRatingModal && (
        <div className="fixed inset-0 z-[110] bg-black/60 backdrop-blur-sm flex items-end justify-center animate-fade-in p-4">
          <div className="bg-white w-full rounded-3xl p-6 space-y-5 shadow-2xl border border-slate-100 animate-slide-up max-w-sm mx-auto mb-10">
            <div className="flex justify-between items-center">
              <h3 className="text-xs font-black text-slate-800 uppercase tracking-wide">
                {language === 'uz' ? "Restoranni baholang" : "Оценить ресторан"}
              </h3>
              <button 
                onClick={() => {
                  hapticFeedback('light');
                  setShowRatingModal(false);
                }}
                className="text-slate-400 font-bold hover:text-slate-600 p-1"
              >
                ✕
              </button>
            </div>

            <div className="flex justify-center gap-2 py-2">
              {[1, 2, 3, 4, 5].map((star) => (
                <button
                  key={star}
                  onClick={() => {
                    hapticFeedback('light');
                    setSelectedRating(star);
                  }}
                  className="p-1 focus:outline-none"
                >
                  <span className={`text-3xl transition-all ${star <= selectedRating ? 'text-amber-400 scale-110' : 'text-slate-200'}`}>
                    ★
                  </span>
                </button>
              ))}
            </div>

            <div className="space-y-1">
              <label className="text-[10px] text-slate-400 font-black uppercase tracking-wider block">Fikringiz (Ixtiyoriy)</label>
              <textarea
                value={ratingComment}
                onChange={(e) => setRatingComment(e.target.value)}
                placeholder={language === 'uz' ? "Fikringizni shu yerda qoldiring..." : "Напишите ваш отзыв здесь..."}
                className="w-full text-xs bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 h-20 focus:outline-none focus:border-amber-500 font-semibold"
              />
            </div>

            <button
              onClick={handleSubmitRating}
              disabled={isSubmittingRating}
              className="w-full bg-amber-500 hover:bg-amber-600 text-white font-black text-xs py-3 rounded-2xl transition disabled:opacity-50"
            >
              {isSubmittingRating 
                ? (language === 'uz' ? "Yuborilmoqda..." : "Отправка...") 
                : (language === 'uz' ? "Yuborish" : "Отправить")}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
