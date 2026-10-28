'use client';

import { AlarmClock, CheckCircle2, TriangleAlert } from 'lucide-react';
import { Pill } from '@rizqim/ui';
import type { OrderSla } from '@/lib/api/orders';
import { useT } from '../I18nProvider';
import { slaLook } from './ordersModel';

// The running SLA stage of an order, in words with an icon and a colour (null: nothing to measure now)
export function SlaBadge({ sla, now, skewMs = 0 }: { sla: OrderSla | null; now: number; skewMs?: number }) {
  const t = useT();
  if (!sla) return null;
  const look = slaLook(sla, t, now, skewMs);
  const Icon = look.state === 'late' ? AlarmClock : look.state === 'warning' ? TriangleAlert : CheckCircle2;
  return (
    <Pill tone={look.tone} icon={Icon} className="whitespace-normal">
      <span data-sla={look.state}>{look.text}</span>
    </Pill>
  );
}
