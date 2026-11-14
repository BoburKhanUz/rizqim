'use client';

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { CalendarHeart } from 'lucide-react';
import { EmptyState, PageHeader } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { BanquetPaymentsSection } from './BanquetPaymentsSection';

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

// /banquets/schedule?event=<uuid>: one event's payment schedule on its own page (the events screens link here and may
// embed BanquetPaymentsSection in the event editor too)
export function BanquetScheduleView() {
  const { t } = useI18n();
  const uuid = useSearchParams().get('event') ?? '';

  return (
    <>
      <PageHeader
        title={t('bqp_scheduleTitle')}
        action={
          <Link href="/banquets/report" className="font-bold text-brand underline-offset-2 hover:underline">
            {t('bqp_reportNav')}
          </Link>
        }
      />
      {UUID.test(uuid) ? <BanquetPaymentsSection eventUuid={uuid} /> : <EmptyState icon={CalendarHeart} title={t('bqp_scheduleNotFound')} text={t('bqp_scheduleNotFoundText')} />}
    </>
  );
}
