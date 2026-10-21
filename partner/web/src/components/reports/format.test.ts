import { afterEach, describe, expect, it, vi } from 'vitest';
import { createTranslator } from '@/i18n';
import { saveBlob } from './download';
import { formatBucket, formatDay, formatMonth, labelFor, localDate } from './format';

afterEach(() => vi.unstubAllGlobals());

describe('days of the store\'s calendar', () => {
  it('keeps the day whatever the browser\'s timezone is', () => {
    const date = localDate('2026-10-21');
    expect([date.getFullYear(), date.getMonth(), date.getDate(), date.getHours()]).toEqual([2026, 9, 21, 0]);
    expect(formatDay('2026-10-21', 'uz')).toMatch(/^21-okt/);
    expect(formatDay('2026-03-01', 'uz')).toMatch(/^1-mar/);
  });

  it('labels the buckets of a series', () => {
    const t = createTranslator('uz');
    expect(formatBucket('2026-10-19', 'day', 'uz', t)).toMatch(/^19-okt/);
    expect(formatBucket('2026-10-19', 'week', 'uz', t)).toMatch(/^Hafta: 19-okt/);
    expect(formatBucket('2026-10-01', 'month', 'uz', t)).toBe('okt 2026');
    expect(formatMonth('2026-10-01', 'en')).toBe('Oct 2026');
    expect(formatMonth('2026-10-01', 'ru')).toMatch(/окт/);
  });

  it('names a value of the server, and shows an unknown one as sent', () => {
    const t = createTranslator('uz');
    expect(labelFor(t, 'fin_t_', 'commission')).toBe('Komissiya');
    expect(labelFor(t, 'fin_t_', 'something_new')).toBe('something_new');
    expect(labelFor(t, 'fin_t_', null)).toBe('—');
  });
});

describe('saveBlob', () => {
  it('clicks a temporary link to the file and lets the URL go', () => {
    vi.useFakeTimers();
    const create = vi.fn(() => 'blob:x');
    const revoke = vi.fn();
    vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: create, revokeObjectURL: revoke }));
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
      expect(this.download).toBe('sales.csv');
      expect(this.href).toBe('blob:x');
    });
    saveBlob(new Blob(['a,b']), 'sales.csv');
    expect(click).toHaveBeenCalledTimes(1);
    expect(document.querySelector('a[download]')).toBeNull(); // the link is removed again
    expect(revoke).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1000);
    expect(revoke).toHaveBeenCalledWith('blob:x');
    vi.useRealTimers();
  });
});
