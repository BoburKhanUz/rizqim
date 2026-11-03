import { useEffect, useState } from 'react';
import { Clock } from 'lucide-react';
import api from '../../services/api';
import { fieldClass } from '../ui';
import { formatPickupTime } from '../../lib/pickupTime';
import type { ApiResponse } from '../../types';

// P6-34: the times a branch offers for a pickup later (GET /stores/{store}/branches/{branch}/pickup-slots). `at` is sent
// back as `pickup_at`; `time` is the store's clock, `business_date` the day it belongs to.
export interface PickupSlot {
  at: string;
  time: string;
  business_date: string;
}

interface PickupSlots {
  enabled: boolean;
  min_lead_minutes: number;
  slot_minutes: number;
  slots: PickupSlot[];
}

interface PickupTimePickerProps {
  storeId: number;
  branchId: number;
  // The chosen slot's `at`, or null for "as soon as possible"
  value: string | null;
  // `label` is how the slot reads ("Bugun, 18:30"), for the checkout summary; null with a null value
  onChange: (value: string | null, label: string | null) => void;
}

// "Olib ketish vaqti": shown only when the branch takes orders for later. "Hozir" (as soon as possible) is the default; a slot
// reads "Bugun, 18:30", "Ertaga, 09:00" or "23-okt, 09:00" (the day of the instant, so a slot after midnight of a 24/7 branch is
// tomorrow). The server checks the time again when the order is placed.
export function PickupTimePicker({ storeId, branchId, value, onChange }: PickupTimePickerProps) {
  const key = `${storeId}:${branchId}`;
  // The answer for the branch it was asked for: another branch shows nothing until its own answer comes
  const [loaded, setLoaded] = useState<{ key: string; data: PickupSlots | null } | null>(null);
  const data = loaded?.key === key ? loaded.data : null;

  useEffect(() => {
    let alive = true;
    api
      .get<ApiResponse<PickupSlots>>(`/stores/${storeId}/branches/${branchId}/pickup-slots`)
      .then((res) => {
        if (alive) setLoaded({ key: `${storeId}:${branchId}`, data: res.data.success ? (res.data.data ?? null) : null });
      })
      .catch(() => {
        // No slots: the order goes as soon as possible, as before
        if (alive) setLoaded({ key: `${storeId}:${branchId}`, data: null });
      });
    return () => {
      alive = false;
    };
  }, [storeId, branchId]);

  // A slot the branch no longer offers (the list was refreshed) falls back to "Hozir"
  useEffect(() => {
    if (value !== null && data !== null && !data.slots.some((slot) => slot.at === value)) onChange(null, null);
  }, [data, value, onChange]);

  if (data === null || !data.enabled || data.slots.length === 0) return null;
  const now = new Date();
  const labelOf = (slot: PickupSlot) => formatPickupTime(slot.at, 'uz', slot.time, now);

  return (
    <label className="flex flex-col gap-1.5">
      <span className="flex items-center gap-1.5 text-footnote text-fg-muted">
        <Clock aria-hidden="true" className="size-4" /> Olib ketish vaqti
      </span>
      <select
        value={value ?? ''}
        onChange={(event) => {
          const slot = data.slots.find((candidate) => candidate.at === event.target.value);
          onChange(slot ? slot.at : null, slot ? labelOf(slot) : null);
        }}
        className={fieldClass}
        aria-label="Olib ketish vaqti"
      >
        <option value="">Hozir (tayyor bo'lishi bilan)</option>
        {data.slots.map((slot) => (
          <option key={slot.at} value={slot.at}>
            {labelOf(slot)}
          </option>
        ))}
      </select>
      <span className="text-footnote text-fg-muted">Eng erta: {data.min_lead_minutes} daqiqadan keyin.</span>
    </label>
  );
}
