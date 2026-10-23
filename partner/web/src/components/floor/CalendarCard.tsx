'use client';

import { useState } from 'react';
import { X } from 'lucide-react';
import { Button, Card, formatDate } from '@rizqim/ui';
import { useSaveTariffCalendar, type TariffCalendar } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Check } from '../management/fields';
import { FormError, Notice } from '../management/Notice';
import { DateField } from '../pos/fields';
import { useServerDraft } from '../pos/serverDraft';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';
import { localDate } from '../reports/format';

const DAYS = [1, 2, 3, 4, 5, 6, 7] as const;
const isDay = (value: string) => /^\d{4}-\d{2}-\d{2}$/.test(value);

// The branch's calendar: which weekdays are the weekend (ISO 1 Monday ... 7 Sunday) and the holidays. A day that is a holiday
// is priced with the "holiday" tariff, else the weekend one, else the weekday one. The holidays are also edited in the POS
// settings: newer data read from the server moves under the edits (what was not touched takes it, see useServerDraft).
const draftOf = (calendar: TariffCalendar) => ({ weekend: [...calendar.weekend_days].sort(), holidays: [...calendar.holidays].sort() });

export function CalendarCard({ branchId, calendar }: { branchId: number; calendar: TariffCalendar }) {
  const { t, language } = useI18n();
  const { draft, setDraft, dirty } = useServerDraft(calendar, draftOf);
  const { weekend, holidays } = draft;
  const setWeekend = (next: number[]) => setDraft((current) => ({ ...current, weekend: next }));
  const setHolidays = (next: string[]) => setDraft((current) => ({ ...current, holidays: next }));
  const [adding, setAdding] = useState('');
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [notice, setNotice] = useState<string | null>(null);
  const save = useSaveTariffCalendar();

  const addHoliday = () => {
    if (!isDay(adding) || holidays.includes(adding)) return;
    setHolidays([...holidays, adding].sort());
    setAdding('');
    setNotice(null);
  };

  return (
    <Card className="flex flex-col gap-4" aria-label={t('floor_cal_title')}>
      <div>
        <h2 className="text-headline font-bold text-fg">{t('floor_cal_title')}</h2>
        <p className="text-footnote text-fg-muted">{t('floor_cal_intro')}</p>
      </div>
      <Notice message={notice} />
      <fieldset className="flex flex-wrap gap-x-4">
        <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('floor_cal_weekend')}</legend>
        {DAYS.map((day) => (
          <Check
            key={day}
            checked={weekend.includes(day)}
            onChange={(on) => {
              setWeekend(on ? [...weekend, day].sort() : weekend.filter((item) => item !== day));
              setNotice(null);
            }}
            label={t(`floor_cal_d${day}`)}
          />
        ))}
      </fieldset>
      <div className="flex flex-col gap-2">
        <span className="text-footnote font-bold text-fg-muted">{t('floor_cal_holidays')}</span>
        <div className="flex flex-wrap items-end gap-3">
          <DateField label={t('floor_cal_addDate')} value={adding} onChange={setAdding} />
          <Button variant="secondary" onClick={addHoliday} disabled={!isDay(adding) || holidays.includes(adding)}>
            {t('floor_cal_add')}
          </Button>
        </div>
        {holidays.length === 0 ? (
          <p className="text-footnote text-fg-muted">{t('floor_cal_noHolidays')}</p>
        ) : (
          <ul className="flex flex-wrap gap-2">
            {holidays.map((day) => (
              <li key={day} className="inline-flex items-center gap-1 rounded-full bg-surface-2 py-0.5 pl-3 pr-1 text-footnote text-fg">
                {formatDate(localDate(day), language)} · {day}
                <button
                  type="button"
                  aria-label={t('floor_cal_removeAria', { date: day })}
                  onClick={() => {
                    setHolidays(holidays.filter((item) => item !== day));
                    setNotice(null);
                  }}
                  className="inline-flex size-8 items-center justify-center rounded-full text-fg-muted hover:bg-line focus-visible:outline-2 focus-visible:outline-brand"
                >
                  <X aria-hidden="true" className="size-4" />
                </button>
              </li>
            ))}
          </ul>
        )}
        <p className="text-caption text-fg-muted">{t('floor_cal_posNote')}</p>
      </div>
      <FormError message={errors.form ?? errors.fields.holidays ?? errors.fields.weekend_days} />
      <div>
        <Button
          loading={save.isPending}
          disabled={!dirty}
          onClick={() => {
            setErrors(NO_ERRORS);
            save.mutate(
              { branchId, weekend_days: weekend, holidays },
              { onSuccess: () => setNotice(t('floor_cal_saved')), onError: (error) => setErrors(sortServerErrors(error, ['weekend_days', 'holidays'], t)) },
            );
          }}
        >
          {t('floor_cal_save')}
        </Button>
      </div>
    </Card>
  );
}
