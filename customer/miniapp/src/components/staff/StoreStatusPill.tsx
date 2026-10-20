import { AlertTriangle, CheckCircle2, PauseCircle } from 'lucide-react';
import { useLanguageStore } from '../../store/languageStore';
import { Pill } from './Pill';

// The state of a store as the platform sees it (OwnerStoreSettings.status): active, waiting for a payment, suspended.
// Says it in words and an icon, not by colour alone.
export function StoreStatusPill({ status }: { status: string | null | undefined }) {
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  if (status === 'active') return <Pill tone="success" icon={CheckCircle2}>{uz ? 'Faol' : 'Активен'}</Pill>;
  if (status === 'payment_required') return <Pill tone="danger" icon={AlertTriangle}>{uz ? "To'lov kutilmoqda" : 'Требуется оплата'}</Pill>;
  if (status === 'suspended') return <Pill tone="warning" icon={PauseCircle}>{uz ? 'Muzlatilgan' : 'Приостановлен'}</Pill>;
  return status ? <Pill>{status}</Pill> : null;
}
