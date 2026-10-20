import { hapticFeedback } from '../../lib/telegram';
import { useCartStore, type PendingCartItem } from '../../store/cartStore';
import { useLanguageStore } from '../../store/languageStore';
import { Button, Dialog } from '../ui';

interface ReplaceCartDialogProps {
  // The item that could not be added because the cart holds another store's food; null: closed
  pending: PendingCartItem | null;
  onClose: () => void;
}

// "The cart has food of another restaurant: empty it and add this one?" The one place that asks it (home, store and
// category pages).
export function ReplaceCartDialog({ pending, onClose }: ReplaceCartDialogProps) {
  const { language } = useLanguageStore();
  const forceClearAndAdd = useCartStore((state) => state.forceClearAndAdd);
  const uz = language === 'uz';

  return (
    <Dialog
      open={pending !== null}
      variant="center"
      role="alertdialog"
      hideClose
      onClose={onClose}
      title={uz ? 'Boshqa oshxona' : 'Другой ресторан'}
      description={
        uz
          ? "Savatda boshqa oshxonaning taomlari bor. Savatni tozalab, shu taomni qo'shishni xohlaysizmi?"
          : 'В корзине есть блюда другого ресторана. Очистить корзину и добавить это блюдо?'
      }
      footer={
        <>
          <Button
            variant="secondary"
            onClick={() => {
              hapticFeedback('light');
              onClose();
            }}
          >
            {uz ? "Yo'q, qaytish" : 'Нет, вернуться'}
          </Button>
          <Button
            data-autofocus
            onClick={() => {
              if (pending) {
                forceClearAndAdd(pending.item, pending.storeInfo);
                hapticFeedback('success');
              }
              onClose();
            }}
          >
            {uz ? 'Ha, tozalash' : 'Да, очистить'}
          </Button>
        </>
      }
    />
  );
}
