import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { hapticFeedback } from '../lib/telegram';

export default function HeroPromoSlider({ promos = [] }: { promos?: any[] }) {
  const navigate = useNavigate();
  const [activeIndex, setActiveIndex] = useState(0);
  const [touchStart, setTouchStart] = useState<number | null>(null);
  const [touchEnd, setTouchEnd] = useState<number | null>(null);

  const minSwipeDistance = 50;

  const onTouchStart = (e: React.TouchEvent) => {
    setTouchEnd(null);
    setTouchStart(e.targetTouches[0].clientX);
  };

  const onTouchMove = (e: React.TouchEvent) => {
    setTouchEnd(e.targetTouches[0].clientX);
  };

  const onTouchEnd = () => {
    if (!touchStart || !touchEnd) return;
    const distance = touchStart - touchEnd;
    const isLeftSwipe = distance > minSwipeDistance;
    const isRightSwipe = distance < -minSwipeDistance;

    if (isLeftSwipe) {
      hapticFeedback('light');
      setActiveIndex((prev) => (prev + 1) % promos.length);
    } else if (isRightSwipe) {
      hapticFeedback('light');
      setActiveIndex((prev) => (prev - 1 + promos.length) % promos.length);
    }
  };

  useEffect(() => {
    if (!promos || promos.length === 0) return;
    const interval = setInterval(() => {
      setActiveIndex((prev) => (prev + 1) % promos.length);
    }, 5000);
    return () => clearInterval(interval);
  }, [promos]);

  if (!promos || promos.length === 0) return null;

  return (
    <div 
      onTouchStart={onTouchStart}
      onTouchMove={onTouchMove}
      onTouchEnd={onTouchEnd}
      className="relative overflow-hidden rounded-3xl mx-4 my-2 h-44 shadow-lg shadow-slate-100 bg-slate-900 group"
    >
      <div 
        className="flex transition-transform duration-700 ease-out h-full"
        style={{ transform: `translateX(-${activeIndex * 100}%)` }}
      >
        {promos.map((banner) => {
          const isClickable = !!banner.store_uuid;
          return (
            <div 
              key={banner.id} 
              onClick={() => {
                if (isClickable) {
                  hapticFeedback('medium');
                  if (banner.product_id) {
                    navigate(`/store/${banner.store_uuid}?product_id=${banner.product_id}`);
                  } else {
                    navigate(`/store/${banner.store_uuid}`);
                  }
                }
              }}
              className={`w-full h-full flex-shrink-0 relative ${isClickable ? 'cursor-pointer' : ''}`}
            >
              <img src={banner.image_url} alt="" className="absolute inset-0 w-full h-full object-cover" />
              {/* Overlay for legibility */}
              <div className="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/40 to-slate-950/10"></div>
              
              <div className="absolute inset-x-0 bottom-0 p-5 text-white z-10 flex flex-col items-start space-y-1">
                {isClickable && (
                  <span className="text-[8px] uppercase font-black tracking-widest bg-amber-500 text-white px-2 py-0.5 rounded-md">
                    AKSIYA 🎁
                  </span>
                )}
                <h3 className="text-sm font-black leading-tight drop-shadow-sm line-clamp-2">{banner.title}</h3>
              </div>
            </div>
          );
        })}
      </div>
      
      {promos.length > 1 && (
        <div className="absolute bottom-3 right-4 flex space-x-1.5 z-20">
          {promos.map((_, idx) => {
            return (
              <button
                key={idx}
                onClick={() => setActiveIndex(idx)}
                className={`h-1.5 rounded-full transition-all duration-300 ${activeIndex === idx ? 'w-5 bg-amber-500' : 'w-1.5 bg-white/50'}`}
              ></button>
            );
          })}
        </div>
      )}
    </div>
  );
}
