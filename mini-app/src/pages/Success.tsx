import { useState, useEffect } from 'react';
import { useLocation, useNavigate, Navigate } from 'react-router-dom';
import { hapticFeedback } from '../lib/telegram';
import { useLanguageStore } from '../store/languageStore';
import { 
  CheckCircle2, Clock, Bike, Utensils, MapPin, Phone, 
  ChevronRight, Sparkles 
} from 'lucide-react';

// The order the cart just created ("/success" is opened with its uuid as router state)
function getOrderUuidFromState(state: unknown): string | undefined {
  if (typeof state === 'object' && state !== null && 'order_uuid' in state && typeof state.order_uuid === 'string') {
    return state.order_uuid;
  }
  return undefined;
}

export default function Success() {
  const location = useLocation();
  const navigate = useNavigate();
  const { language } = useLanguageStore();
  
  // In a real app, this would be fetched/synced via WebSockets or polling
  const [currentStep, setCurrentStep] = useState(1);
  const [timeLeft, setTimeLeft] = useState(24);

  const orderUuid = getOrderUuidFromState(location.state);

  useEffect(() => {
    // Mocking real-time tracking progression for demonstration
    const timer1 = setTimeout(() => {
      setCurrentStep(2);
      setTimeLeft(18);
      hapticFeedback('light');
    }, 5000);
    
    const timer2 = setTimeout(() => {
      setCurrentStep(3);
      setTimeLeft(8);
      hapticFeedback('light');
    }, 12000);

    return () => { clearTimeout(timer1); clearTimeout(timer2); };
  }, []);

  if (!orderUuid && import.meta.env.PROD) {
    return <Navigate to="/" replace />;
  }

  // The short code when the cart passed it (state.order_number), else the start of the uuid
  const orderNumber = (location.state as { order_number?: unknown } | null)?.order_number;
  const orderId = (typeof orderNumber === 'string' && orderNumber.trim()) || orderUuid?.split('-')[0]?.toUpperCase() || 'NEW';

  const steps = [
    { id: 1, title: language === 'uz' ? 'Tasdiqlandi' : 'Подтвержден', desc: language === 'uz' ? 'Buyurtma qabul qilindi' : 'Заказ принят', icon: <CheckCircle2 className="w-5 h-5" /> },
    { id: 2, title: language === 'uz' ? 'Tayyorlanmoqda' : 'Готовится', desc: language === 'uz' ? 'Oshpazlar ishga kirishdi' : 'Повара начали готовить', icon: <Utensils className="w-5 h-5" /> },
    { id: 3, title: language === 'uz' ? 'Yo\'lda' : 'В пути', desc: language === 'uz' ? 'Kuryer siz tomon kelmoqda' : 'Курьер едет к вам', icon: <Bike className="w-5 h-5" /> },
  ];

  return (
    <div className="min-h-screen bg-slate-50 relative overflow-hidden flex flex-col pb-safe">
      {/* Dynamic Header Map / Visualizer Area */}
      <div className="relative h-[40vh] w-full bg-slate-100 flex flex-col items-center justify-center p-6 text-center z-10 border-b border-slate-200/50 shadow-sm">
        <div className="absolute inset-0 opacity-10" style={{ backgroundImage: 'radial-gradient(#888 1px, transparent 1px)', backgroundSize: '20px 20px' }}></div>
        
        <div className="relative z-20 flex flex-col items-center">
          <div className="w-20 h-20 bg-amber-500/10 rounded-full flex items-center justify-center mb-4 relative">
            <div className="absolute inset-0 rounded-full border-4 border-amber-500 border-t-transparent animate-spin opacity-40"></div>
            <Sparkles className="w-8 h-8 text-amber-500" />
          </div>
          
          <h1 className="text-3xl font-black text-slate-800 tracking-tight mb-1">
            {language === 'uz' ? 'Ajoyib tanlov!' : 'Отличный выбор!'}
          </h1>
          <p className="text-slate-500 font-medium mb-4">
            {language === 'uz' ? 'Buyurtmangiz muvaffaqiyatli qabul qilindi.' : 'Ваш заказ успешно принят.'}
          </p>

          <div className="bg-white border border-slate-100 px-5 py-2.5 rounded-2xl shadow-sm flex items-center gap-3">
            <Clock className="w-5 h-5 text-amber-500 animate-pulse" />
            <div className="text-left">
              <span className="block text-[10px] uppercase font-bold text-slate-400">{language === 'uz' ? 'Taxminiy vaqt' : 'Примерное время'}</span>
              <span className="block text-sm font-black text-slate-800">~ {timeLeft} {language === 'uz' ? 'daqiqa' : 'минут'}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Live Order Tracking Pipeline */}
      <div className="flex-1 bg-white p-6 flex flex-col gap-6 z-20 -mt-4 rounded-t-[32px] relative shadow-inner">
        <div className="w-12 h-1.5 bg-slate-200 rounded-full mx-auto -mt-2"></div>

        <div className="flex justify-between items-center px-1 border-b border-slate-100 pb-4">
          <h2 className="text-lg font-bold text-slate-800">{language === 'uz' ? 'Holat' : 'Статус'}</h2>
          <span className="font-mono text-amber-500 font-black bg-amber-500/10 px-3 py-1 rounded-xl text-sm">
            #{orderId}
          </span>
        </div>

        {/* Vertical Stepper */}
        <div className="flex flex-col gap-0 px-2 relative">
          <div className="absolute left-6 top-8 bottom-10 w-0.5 bg-slate-100 z-0"></div>
          {steps.map((step) => {
            const isActive = currentStep >= step.id;
            const isCurrent = currentStep === step.id;

            return (
              <div key={step.id} className="flex gap-4 items-start relative z-10 py-3">
                <div className={`w-9 h-9 rounded-full flex items-center justify-center shrink-0 border-4 border-white transition-colors duration-500 ${
                  isActive ? 'bg-amber-500 text-white shadow-md shadow-amber-500/30' : 'bg-slate-100 text-slate-400 border-slate-100'
                } ${isCurrent ? 'scale-110 ring-2 ring-amber-500/20' : ''}`}>
                  {step.icon}
                </div>
                
                <div className="flex-1 pt-1.5">
                  <h3 className={`text-sm font-bold transition-colors ${isActive ? 'text-slate-800' : 'text-slate-400'}`}>
                    {step.title}
                  </h3>
                  <p className={`text-[10px] font-medium mt-0.5 transition-colors ${isActive ? 'text-slate-500' : 'text-slate-300'}`}>
                    {step.desc}
                  </p>
                </div>
              </div>
            );
          })}
        </div>

        {/* Courier Details (Visible if step >= 3) */}
        {currentStep >= 3 && (
          <div className="bg-slate-50 border border-slate-100 p-4 rounded-3xl flex items-center justify-between shadow-sm animate-fade-in-up mt-2">
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 bg-slate-200 rounded-full overflow-hidden border-2 border-amber-500/20">
                <img src="https://ui-avatars.com/api/?name=Courier&background=random" alt="Courier" />
              </div>
              <div>
                <h4 className="font-bold text-slate-800 text-sm">Shavkat K.</h4>
                <div className="flex items-center gap-1 text-[10px] text-slate-500 font-medium">
                  <MapPin className="w-3 h-3 text-emerald-500" /> Sizga yaqinlashmoqda...
                </div>
              </div>
            </div>
            <button 
              onClick={() => hapticFeedback('light')}
              className="w-10 h-10 bg-amber-500/10 text-amber-500 rounded-full flex items-center justify-center active:scale-90 transition-transform"
            >
              <Phone className="w-4 h-4 fill-current" />
            </button>
          </div>
        )}
      </div>

      {/* Action Area */}
      <div className="p-4 bg-white border-t border-slate-100 pb-safe">
        <button 
          onClick={() => { hapticFeedback('medium'); navigate('/'); }}
          className="w-full bg-slate-50 text-slate-800 border border-slate-200/50 py-4 rounded-3xl font-bold text-sm active:scale-95 transition-transform flex items-center justify-center gap-2"
        >
          {language === 'uz' ? 'Asosiy menuga qaytish' : 'Вернуться в меню'}
          <ChevronRight className="w-4 h-4 text-slate-400" />
        </button>
      </div>
    </div>
  );
}
