import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate, useLocation } from 'react-router-dom';
import api from '../services/api';
import { hapticFeedback } from '../lib/telegram';
import { getApiErrorMessage } from '../lib/apiError';
import { fetchOrders } from '../hooks/useOrders';
import { useLanguageStore } from '../store/languageStore';
import { useAuthStore } from '../store/authStore';
import type { ApiResponse, Complaint, ComplaintType, NewComplaint } from '../types';
import { 
  ArrowLeft, 
  AlertTriangle, 
  MessageSquare, 
  CheckCircle, 
  Send, 
  HelpCircle,
  ShieldAlert,
  Loader2
} from 'lucide-react';

const COMPLAINT_TYPES: readonly ComplaintType[] = ['WRONG_ITEM', 'ORDER_NOT_RECEIVED', 'QUALITY_ISSUE', 'COURIER_ISSUE', 'PAYMENT_ISSUE', 'OTHER'];

// The order the customer came from ("Shikoyat qilish" on an order passes it as router state)
function getOrderIdFromState(state: unknown): string | undefined {
  if (typeof state === 'object' && state !== null && 'orderId' in state) {
    const orderId = state.orderId;
    if (typeof orderId === 'number' || typeof orderId === 'string') return orderId.toString();
  }
  return undefined;
}

export default function Complaints() {
  const navigate = useNavigate();
  const location = useLocation();
  const { language } = useLanguageStore();
  const { user } = useAuthStore();
  const queryClient = useQueryClient();

  // Retrieve initial orderId from location state or URL search parameters
  const searchParams = new URLSearchParams(location.search);
  const initialOrderId = getOrderIdFromState(location.state) || searchParams.get('orderId') || '';

  const [selectedOrderId, setSelectedOrderId] = useState<string>(initialOrderId);
  const [complaintType, setComplaintType] = useState<ComplaintType>('QUALITY_ISSUE');
  const [description, setDescription] = useState<string>('');
  const [replyText, setReplyText] = useState<Record<number, string>>({});

  // Fetch customer's complaints
  const { data: complaints = [], isLoading: loadingComplaints } = useQuery({
    queryKey: ['complaints'],
    queryFn: async () => {
      const res = await api.get<ApiResponse<Complaint[]>>('/complaints');
      return res.data?.data || [];
    }
  });

  // Fetch recent orders for selection
  const { data: orders = [] } = useQuery({
    queryKey: ['orders'],
    queryFn: fetchOrders
  });

  const userRole = user?.role || 'customer';
  const isBusinessUser = userRole === 'owner' || userRole === 'manager';

  // Mutation to submit complaint
  const submitComplaint = useMutation({
    mutationFn: async (payload: NewComplaint) => {
      const res = await api.post<ApiResponse<Complaint>>('/complaints', payload);
      return res.data;
    },
    onSuccess: () => {
      hapticFeedback('success');
      setDescription('');
      setSelectedOrderId('');
      queryClient.invalidateQueries({ queryKey: ['complaints'] });
      alert(language === 'uz' ? 'Shikoyat muvaffaqiyatli yuborildi!' : 'Жалоба успешно отправлена!');
    },
    onError: (err) => {
      hapticFeedback('rigid');
      alert(getApiErrorMessage(err) || 'Xatolik yuz berdi.');
    }
  });

  // Mutation to reply to complaint
  const replyComplaint = useMutation({
    mutationFn: async ({ id, reply }: { id: number, reply: string }) => {
      const res = await api.post<ApiResponse<Complaint>>(`/complaints/${id}/reply`, { reply });
      return res.data;
    },
    onSuccess: (_, variables) => {
      hapticFeedback('success');
      setReplyText(prev => ({ ...prev, [variables.id]: '' }));
      queryClient.invalidateQueries({ queryKey: ['complaints'] });
      alert(language === 'uz' ? 'Javob muvaffaqiyatli saqlandi!' : 'Ответ успешно сохранен!');
    },
    onError: (err) => {
      hapticFeedback('rigid');
      alert(getApiErrorMessage(err) || 'Xatolik yuz berdi.');
    }
  });

  // Mutation to escalate complaint
  const escalateComplaint = useMutation({
    mutationFn: async (id: number) => {
      const res = await api.post<ApiResponse<Complaint>>(`/complaints/${id}/escalate`);
      return res.data;
    },
    onSuccess: () => {
      hapticFeedback('success');
      queryClient.invalidateQueries({ queryKey: ['complaints'] });
      alert(language === 'uz' ? 'Shikoyat SuperAdmin moderatoriga yuborildi!' : 'Жалоба отправлена модератору SuperAdmin!');
    },
    onError: (err) => {
      hapticFeedback('rigid');
      alert(getApiErrorMessage(err) || 'Xatolik yuz berdi.');
    }
  });

  const handleCreateComplaint = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedOrderId) {
      alert(language === 'uz' ? 'Iltimos, buyurtmani tanlang.' : 'Пожалуйста, выберите заказ.');
      return;
    }
    if (!description.trim()) {
      alert(language === 'uz' ? 'Iltimos, tavsif kiriting.' : 'Пожалуйста, введите описание.');
      return;
    }
    submitComplaint.mutate({
      order_id: parseInt(selectedOrderId),
      type: complaintType,
      description: description.trim()
    });
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'pending':
        return <span className="bg-amber-500/10 text-amber-500 border border-amber-500/20 text-[9px] px-2.5 py-1 rounded-full font-black uppercase">Pending</span>;
      case 'owner_replied':
        return <span className="bg-blue-500/10 text-blue-500 border border-blue-500/20 text-[9px] px-2.5 py-1 rounded-full font-black uppercase">Oshxona javob berdi</span>;
      case 'in_review':
        return <span className="bg-purple-500/10 text-purple-500 border border-purple-500/20 text-[9px] px-2.5 py-1 rounded-full font-black uppercase animate-pulse">In Review</span>;
      case 'resolved':
        return <span className="bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 text-[9px] px-2.5 py-1 rounded-full font-black uppercase">Resolved</span>;
      case 'closed':
        return <span className="bg-slate-500/10 text-slate-500 border border-slate-500/20 text-[9px] px-2.5 py-1 rounded-full font-black uppercase">Closed</span>;
      default:
        return <span className="bg-slate-500/10 text-slate-500 border border-slate-500/20 text-[9px] px-2.5 py-1 rounded-full font-black uppercase">{status}</span>;
    }
  };

  const getTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
      WRONG_ITEM: language === 'uz' ? "Noto'g'ri mahsulot" : 'Неверный товар',
      ORDER_NOT_RECEIVED: language === 'uz' ? 'Buyurtma kelmadi' : 'Заказ не доставлен',
      QUALITY_ISSUE: language === 'uz' ? 'Sifat muammosi' : 'Проблема качества',
      COURIER_ISSUE: language === 'uz' ? 'Kuryer muammosi' : 'Проблема с курьером',
      PAYMENT_ISSUE: language === 'uz' ? 'To\'lov muammosi' : 'Проблема с оплатой',
      OTHER: language === 'uz' ? 'Boshqa' : 'Другое'
    };
    return labels[type] || type;
  };
  const isDark = isBusinessUser;
  const bgClass = isDark ? "bg-gradient-to-br from-bg via-bg to-primary/5 min-h-screen text-text" : "bg-slate-50 min-h-screen text-slate-800";
  const cardClass = isDark ? "bg-secondary border border-white/5 shadow-sm" : "bg-white border border-slate-100 shadow-sm";
  const headerBorderClass = isDark ? "border-white/5" : "border-slate-200";
  const backBtnClass = isDark ? "bg-secondary text-text-h border border-white/5" : "bg-white text-slate-800 border border-slate-100";
  const textTitleClass = isDark ? "text-text-h" : "text-slate-800";
  const textHintClass = isDark ? "text-hint" : "text-slate-400";
  const inputClass = isDark ? "bg-bg border border-white/5 text-text-h outline-none focus:border-primary/50 focus:bg-secondary transition" : "bg-slate-50 border border-slate-200 text-slate-700 outline-none focus:border-amber-500 transition";
  const innerCardClass = isDark ? "bg-bg/40 border border-white/5" : "bg-slate-50 border border-slate-100/50";

  return (
    <div className={`p-4 pb-28 flex flex-col gap-6 ${bgClass}`}>
      {/* Header */}
      <header className={`flex items-center gap-4 pb-2 border-b ${headerBorderClass}`}>
        <button 
          onClick={() => { hapticFeedback('light'); navigate(-1); }}
          className={`p-2 rounded-xl flex items-center justify-center shadow-sm active:scale-90 transition ${backBtnClass}`}
        >
          <ArrowLeft className="w-5 h-5" />
        </button>
        <div>
          <h1 className={`text-xl font-bold ${textTitleClass}`}>
            {isBusinessUser 
              ? (language === 'uz' ? 'Shikoyat va So\'rovlar' : 'Жалобы и Споры')
              : (language === 'uz' ? 'Mening shikoyatlarim' : 'Мои жалобы')
            }
          </h1>
          <span className={`text-[10px] font-medium ${textHintClass}`}>
            {isBusinessUser ? 'Mijozlar tomonidan qoldirilgan e\'tirozlar' : 'Sifat va xizmat yuzasidan e\'tiroz bildirish'}
          </span>
        </div>
      </header>

      {/* Main Grid: Create Complaint form for customers */}
      {!isBusinessUser && (
        <section className="bg-white p-5 rounded-3xl border border-slate-100 shadow-sm flex flex-col gap-4">
          <div className="flex items-center gap-2 text-slate-800">
            <AlertTriangle className="w-5 h-5 text-amber-500" />
            <h2 className="text-sm font-black uppercase tracking-wider">Shikoyat yuborish</h2>
          </div>

          <form onSubmit={handleCreateComplaint} className="flex flex-col gap-4">
            {/* Select Order */}
            <div className="flex flex-col gap-1.5">
              <label className="text-[10px] font-black text-slate-400 uppercase">Buyurtmani tanlang</label>
              <select
                value={selectedOrderId}
                onChange={(e) => setSelectedOrderId(e.target.value)}
                className="bg-slate-50 border border-slate-200 p-3 rounded-2xl text-xs font-semibold text-slate-700 outline-none focus:border-amber-500 transition"
              >
                <option value="">-- Buyurtma tanlash --</option>
                {orders.map((order) => (
                  <option key={order.id} value={order.id}>
                    #{order.short_code || order.uuid.substring(0, 8)} ({order.total_price.toLocaleString()} UZS)
                  </option>
                ))}
              </select>
            </div>

            {/* Select Complaint Type */}
            <div className="flex flex-col gap-1.5">
              <label className="text-[10px] font-black text-slate-400 uppercase">Muammo turi</label>
              <div className="grid grid-cols-2 gap-2">
                {COMPLAINT_TYPES.map((type) => (
                  <button
                    key={type}
                    type="button"
                    onClick={() => { hapticFeedback('light'); setComplaintType(type); }}
                    className={`py-2 px-3 rounded-xl text-[10px] font-bold border transition text-center ${
                      complaintType === type 
                        ? 'bg-amber-500 border-amber-500 text-white shadow-sm' 
                        : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'
                    }`}
                  >
                    {getTypeLabel(type)}
                  </button>
                ))}
              </div>
            </div>

            {/* Description */}
            <div className="flex flex-col gap-1.5">
              <label className="text-[10px] font-black text-slate-400 uppercase">Batafsil tushuntiring</label>
              <textarea
                rows={3}
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Muammo haqida batafsil ma'lumot qoldiring..."
                className="bg-slate-50 border border-slate-200 p-4 rounded-2xl text-xs font-semibold text-slate-700 outline-none focus:border-amber-500 transition resize-none"
              />
            </div>

            {/* Submit Button */}
            <button
              type="submit"
              disabled={submitComplaint.isPending}
              className="bg-amber-500 text-white py-3.5 rounded-2xl font-black text-xs shadow-md shadow-amber-500/10 active:scale-95 transition-transform flex items-center justify-center gap-2 disabled:opacity-75 disabled:active:scale-100"
            >
              {submitComplaint.isPending ? (
                <Loader2 className="w-4 h-4 animate-spin" />
              ) : (
                <>
                  <Send className="w-3.5 h-3.5" />
                  <span>Yuborish</span>
                </>
              )}
            </button>
          </form>
        </section>
      )}

      {/* Complaints List */}
      <section className="flex flex-col gap-3">
        <h2 className={`text-xs uppercase font-black tracking-wider ${textHintClass}`}>
          {isBusinessUser ? 'Kelgan shikoyatlar' : 'Yuborilgan shikoyatlar tarixi'}
        </h2>

        {loadingComplaints ? (
          <div className="flex justify-center py-10">
            <Loader2 className={`w-8 h-8 animate-spin ${isDark ? 'text-primary' : 'text-amber-500'}`} />
          </div>
        ) : complaints.length === 0 ? (
          <div className={`p-8 rounded-3xl text-center text-xs font-semibold ${cardClass} ${textHintClass}`}>
            <HelpCircle className="w-10 h-10 mx-auto mb-2 text-slate-300" />
            Shikoyatlar mavjud emas
          </div>
        ) : (
          <div className="flex flex-col gap-4">
            {complaints.map((complaint) => (
              <div 
                key={complaint.id} 
                className={`p-5 rounded-3xl flex flex-col gap-3.5 ${cardClass}`}
              >
                <div className="flex items-center justify-between">
                  <div className="flex flex-col gap-0.5">
                    <span className={`text-[10px] font-black ${textHintClass}`}>ORDER #{complaint.order?.short_code || complaint.order_id}</span>
                    <span className={`text-xs font-black ${textTitleClass}`}>{getTypeLabel(complaint.type)}</span>
                  </div>
                  {getStatusBadge(complaint.status)}
                </div>

                <div className={`p-3 rounded-2xl border ${innerCardClass}`}>
                  <p className={`text-xs font-semibold leading-relaxed ${isDark ? 'text-text-h' : 'text-slate-600'}`}>
                    {complaint.description}
                  </p>
                </div>

                {/* Complaint Reply */}
                {complaint.reply ? (
                  <div className={`p-3.5 rounded-2xl flex flex-col gap-1 border ${
                    isDark 
                      ? 'bg-blue-500/10 border-blue-500/20 text-blue-200' 
                      : 'bg-blue-50/50 border-blue-100/60 text-blue-900'
                  }`}>
                    <span className="text-[9px] font-black text-blue-500 uppercase flex items-center gap-1">
                      <MessageSquare className="w-3 h-3" /> Cafe / Admin Javobi
                    </span>
                    <p className={`text-xs font-semibold leading-relaxed ${isDark ? 'text-blue-300' : 'text-blue-900'}`}>
                      {complaint.reply}
                    </p>
                  </div>
                ) : isBusinessUser ? (
                  <div className="flex flex-col gap-2 pt-2 border-t border-white/5">
                    <textarea
                      rows={2}
                      value={replyText[complaint.id] || ''}
                      onChange={(e) => setReplyText(prev => ({ ...prev, [complaint.id]: e.target.value }))}
                      placeholder="Mijozga javob yozing..."
                      className={`p-3 rounded-2xl text-xs font-semibold outline-none transition resize-none ${inputClass}`}
                    />
                    <button
                      onClick={() => {
                        const txt = replyText[complaint.id];
                        if (!txt || !txt.trim()) return;
                        replyComplaint.mutate({ id: complaint.id, reply: txt.trim() });
                      }}
                      disabled={replyComplaint.isPending}
                      className="bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black px-4 py-2.5 rounded-xl transition flex items-center justify-center gap-1.5 self-end active:scale-95"
                    >
                      <CheckCircle className="w-3.5 h-3.5" />
                      <span>Javob Yuborish</span>
                    </button>
                  </div>
                ) : (
                  <div className={`text-[10px] italic ${textHintClass}`}>
                    Javob kutilmoqda...
                  </div>
                )}

                {/* Escalation Button for customer if owner replied */}
                {!isBusinessUser && complaint.status === 'owner_replied' && (
                  <div className="flex justify-end pt-2 border-t border-slate-100">
                    <button
                      onClick={() => {
                        if (confirm(language === 'uz' ? "Shikoyatni SuperAdmin moderatorga yuborishni xohlaysizmi?" : "Эскалировать жалобу в SuperAdmin?")) {
                          escalateComplaint.mutate(complaint.id);
                        }
                      }}
                      className="flex items-center gap-1 bg-purple-500 hover:bg-purple-600 text-white text-[10px] font-black px-3 py-2 rounded-xl transition active:scale-95"
                    >
                      <ShieldAlert className="w-3.5 h-3.5" />
                      <span>Moderatorga yuborish (Escalate)</span>
                    </button>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
