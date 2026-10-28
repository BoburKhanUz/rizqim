import { describe, expect, it } from 'vitest';
import { createTranslator } from '@/i18n';
import { ApiError } from '@/lib/api/client';
import { ago, elapsed, monitoringError, severityText, span, verdictText, verdictTone } from './words';

const t = createTranslator('uz');
const NOW = Date.parse('2026-10-27T12:00:00Z');

describe('monitoring words', () => {
  it('says a length of time in the biggest whole unit', () => {
    expect(span(5, t)).toBe('5 soniya');
    expect(span(59.6, t)).toBe('1 daqiqa');
    expect(span(125, t)).toBe('2 daqiqa');
    expect(span(3 * 3600 + 20 * 60, t)).toBe('3 soat');
    expect(span(2 * 86400 + 5, t)).toBe('2 kun');
    expect(span(-4, t)).toBe('0 soniya');
  });

  it('says how long ago a moment was', () => {
    expect(ago('2026-10-27T11:55:00Z', t, NOW)).toBe('5 daqiqa oldin');
    expect(ago('2026-10-27T11:59:50Z', t, NOW)).toBe('hozirgina');
    expect(ago(null, t, NOW)).toBe('—');
    expect(ago('not a date', t, NOW)).toBe('—');
    expect(elapsed('2026-10-27T09:00:00Z', t, NOW)).toBe('3 soat');
  });

  it('gives every verdict a word and a tone, with an unknown one as a warning', () => {
    expect(verdictText('ok', t)).toBe('Ishlayapti');
    expect(verdictText('fail', t)).toBe('Ishlamayapti');
    expect(verdictText('not_used', t)).toBe('Ishlatilmaydi');
    expect(verdictText('something_new', t)).toBe("Noma'lum");
    expect(verdictTone('ok')).toBe('success');
    expect(verdictTone('fail')).toBe('danger');
    expect(verdictTone('not_configured')).toBe('neutral');
    expect(verdictTone('stale')).toBe('warning');
    expect(severityText('critical', t)).toBe('Kritik');
  });

  it('knows the monitoring refusals and falls back to the shared ones', () => {
    expect(monitoringError(new ApiError('', 409, 'alert_not_open'), t)).toContain('allaqachon qabul qilingan');
    expect(monitoringError(new ApiError('', 409, 'alert_resolved'), t)).toContain("allaqachon hal bo'lgan");
    expect(monitoringError(new ApiError('', 404, 'not_found'), t, 'monitoring_jobs_gone')).toContain('topilmadi');
    expect(monitoringError(new ApiError('', 403, 'forbidden'), t)).toBe("Bu amal uchun ruxsatingiz yo'q.");
  });
});
