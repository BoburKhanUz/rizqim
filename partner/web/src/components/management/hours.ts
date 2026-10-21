import type { ManagedBranch } from '@rizqim/staff';
import type { Translate } from '@/i18n';

// "09:00:00" and "09:00" are the same time of day on the screen
export const clock = (value: string | null | undefined): string => (value ?? '').slice(0, 5);

// The working hours of a branch in words: one daily window, or "24 soat"; a window that closes after midnight says so.
// `all_day` and `overnight` are what the server worked out (the same rule the delivery check uses).
export function hoursText(hours: ManagedBranch['working_hours'], t: Translate): string {
  if (hours.all_day) return t('branches_allDay');
  const window = `${clock(hours.open)}–${clock(hours.close)}`;
  return hours.overnight ? `${window} (${t('branches_overnight')})` : window;
}
