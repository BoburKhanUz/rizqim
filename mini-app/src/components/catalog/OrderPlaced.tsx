import { CheckCircle2 } from 'lucide-react';
import { Link } from 'react-router-dom';
import { hapticFeedback } from '../../lib/telegram';
import { useLanguageStore } from '../../store/languageStore';
import { buttonClasses } from '../ui';

// "Your order was placed": the cart shows it right after checkout, and /success shows it for an order opened by id.
// Only what is true is said: the order number the server gave, and where to follow the order. No made-up progress.
export function OrderPlaced({ orderNumber }: { orderNumber?: string }) {
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  return (
    <div className="flex flex-col items-center gap-6 px-6 pb-4 pt-16 text-center animate-fade-in">
      <span className="flex size-20 items-center justify-center rounded-full bg-success-soft text-success-soft-fg animate-scale-up">
        <CheckCircle2 aria-hidden="true" className="size-10" />
      </span>
      <div className="flex flex-col gap-2">
        <h1 className="text-title font-bold text-fg">{uz ? 'Buyurtma qabul qilindi!' : 'Заказ принят!'}</h1>
        {orderNumber && (
          <p className="text-body text-fg-muted">
            {uz ? 'Buyurtma raqami' : 'Номер заказа'}: <span className="font-bold text-fg">#{orderNumber}</span>
          </p>
        )}
        <p className="mx-auto max-w-xs text-footnote text-fg-muted">
          {uz
            ? "Buyurtma holatini «Buyurtmalarim» bo'limida kuzatishingiz mumkin."
            : 'Статус заказа можно отслеживать в разделе «Мои заказы».'}
        </p>
      </div>
      <div className="flex w-full max-w-xs flex-col gap-2">
        <Link to="/orders" onClick={() => hapticFeedback('medium')} className={buttonClasses({ size: 'lg', fullWidth: true })}>
          {uz ? 'Buyurtmani kuzatish' : 'Отследить заказ'}
        </Link>
        <Link to="/" onClick={() => hapticFeedback('light')} className={buttonClasses({ variant: 'secondary', size: 'lg', fullWidth: true })}>
          {uz ? 'Katalogga qaytish' : 'В каталог'}
        </Link>
      </div>
    </div>
  );
}
