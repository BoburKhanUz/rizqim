import { ChevronRight } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { selectionChanged } from '../../lib/twa';
import { useStaffText } from '../../hooks/useStaffText';
import { ORDER_TEXT } from './orderText';

// "Batafsil >" at the foot of an order card: opens the order's own screen (GET /staff/orders/{uuid}).
export function OrderDetailsLink({ uuid, code }: { uuid: string; code: string }) {
  const navigate = useNavigate();
  const tx = useStaffText(ORDER_TEXT);
  return (
    <button
      type="button"
      aria-label={tx('detailsOf', { code })}
      onClick={() => {
        selectionChanged();
        navigate(`/manager/orders/${uuid}`);
      }}
      className="-mx-1 -mb-1 flex min-h-11 cursor-pointer items-center justify-center gap-1 rounded-control border-t border-line px-1 text-footnote font-bold text-brand focus-visible:outline-2 focus-visible:outline-brand"
    >
      {tx('details')}
      <ChevronRight aria-hidden="true" className="size-4" />
    </button>
  );
}
