'use client';

import { BanquetOpsSection } from '../banquetops/BanquetOpsSection';
import { BanquetPaymentsSection } from '../banquetpay/BanquetPaymentsSection';
import type { BanquetEvent } from './api';

// What the other banquet screens show on the event page, below the event itself (one line each): the payment schedule
// (P6-53, components/banquetpay). The production sheet, the settlement and the contract (P6-54) add theirs here.
export function BanquetEventSections({ event }: { event: BanquetEvent }) {
  return (
    <>
      <BanquetPaymentsSection eventUuid={event.uuid} />
      <BanquetOpsSection eventUuid={event.uuid} />
    </>
  );
}
