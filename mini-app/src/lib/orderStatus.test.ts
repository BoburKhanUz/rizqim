import { afterEach, describe, expect, it } from 'vitest';
import { useLanguageStore, type Language } from '../store/languageStore';
import type { OrderStatus } from '../types';
import { getOrderStatusLook } from './orderStatus';

// Every value of App\Enums\OrderStatus. A Record, so that a status added to the OrderStatus type fails to compile
// here until it is covered by these tests too.
const BACKEND_STATUSES: Record<OrderStatus, true> = {
  new: true,
  accepted: true,
  preparing: true,
  ready: true,
  courier_assigned: true,
  on_the_way: true,
  delivery: true,
  delivered: true,
  canceled: true,
  rejected: true,
  refunded: true,
};
const STATUSES = Object.keys(BACKEND_STATUSES) as OrderStatus[];
const LANGUAGES: Language[] = ['uz', 'ru', 'en'];

// delivery is the legacy name of on_the_way: the customer sees the same thing for both
const ALIASES: OrderStatus[] = ['on_the_way', 'delivery'];

afterEach(() => {
  useLanguageStore.setState({ language: 'uz' });
});

function labelOf(status: OrderStatus, language: Language): string {
  useLanguageStore.setState({ language });
  const { labelKey } = getOrderStatusLook(status);
  if (!labelKey) throw new Error(`${status} has no label key`);
  return useLanguageStore.getState().t(labelKey);
}

// status -> label for one language
const labelsIn = (language: Language) =>
  Object.fromEntries(STATUSES.map((status) => [status, labelOf(status, language)])) as Record<OrderStatus, string>;

describe('getOrderStatusLook: every status of the backend', () => {
  it.each(STATUSES)('%s has its own label key and colours', (status) => {
    const look = getOrderStatusLook(status);

    expect(look.labelKey).toBe(`status_${status}`);
    expect(look.staffLabelKey).toBe(`staffStatus_${status}`);
    // design tokens only: a soft fill and its text colour, never a raw palette class
    expect(look.badge).toMatch(/^bg-[\w-]+ text-[\w-]+$/);
    expect(look.badge).not.toMatch(/slate|amber|white|\d{3}/);
  });

  it.each(LANGUAGES)('has a translated label for each one in %s', (language) => {
    const labels = labelsIn(language);

    for (const status of STATUSES) {
      expect(labels[status], `${status} in ${language}`).toBeTruthy();
      expect(labels[status], `${status} in ${language}`).not.toMatch(/^status_/); // not a missing-key fallback
    }
  });

  it('writes Russian in Cyrillic and English in English, not the Uzbek text that a missing key falls back to', () => {
    const uz = labelsIn('uz');
    const ru = labelsIn('ru');
    const en = labelsIn('en');

    for (const status of STATUSES) {
      expect(ru[status], status).toMatch(/^[А-Яа-яЁё ]+$/);
      expect(en[status], status).not.toBe(uz[status]);
      expect(en[status], status).toMatch(/^[A-Za-z ]+$/);
    }
  });

  it.each(LANGUAGES)('tells the statuses apart in %s: only on_the_way and delivery read the same', (language) => {
    const labels = labelsIn(language);
    const statusesByLabel = new Map<string, OrderStatus[]>();
    for (const status of STATUSES) {
      statusesByLabel.set(labels[status], [...(statusesByLabel.get(labels[status]) ?? []), status]);
    }

    const shared = [...statusesByLabel.values()].filter((group) => group.length > 1);
    expect(shared).toEqual([ALIASES]);
  });

  it('puts each status in one of four semantic groups (or neutral)', () => {
    const tones = Object.fromEntries(STATUSES.map((status) => [status, getOrderStatusLook(status).tone]));
    expect(tones).toEqual({
      new: 'warning',
      accepted: 'info',
      preparing: 'info',
      ready: 'info',
      courier_assigned: 'info',
      on_the_way: 'info',
      delivery: 'info',
      delivered: 'success',
      canceled: 'danger',
      rejected: 'danger',
      refunded: 'neutral',
    });
  });

  it('tells statuses of one colour apart by their icon, except the two names of the same stage', () => {
    const statusesByIcon = new Map<unknown, OrderStatus[]>();
    for (const status of STATUSES) {
      const { icon } = getOrderStatusLook(status);
      statusesByIcon.set(icon, [...(statusesByIcon.get(icon) ?? []), status]);
    }

    const shared = [...statusesByIcon.values()].filter((group) => group.length > 1);
    expect(shared).toEqual([ALIASES]);
  });

  it.each(LANGUAGES)('has a staff label for every status in %s', (language) => {
    useLanguageStore.setState({ language });
    for (const status of STATUSES) {
      const { staffLabelKey } = getOrderStatusLook(status);
      expect(useLanguageStore.getState().t(staffLabelKey!), `${status} in ${language}`).not.toMatch(/^staffStatus_/);
    }
  });

  it.each(['ready', 'courier_assigned', 'on_the_way', 'delivery', 'rejected', 'refunded', 'canceled'] as const)(
    '%s is not shown as "pending" (the look of a new order)',
    (status) => {
      const pending = getOrderStatusLook('new');

      expect(getOrderStatusLook(status).labelKey).not.toBe(pending.labelKey);
      expect(getOrderStatusLook(status).tone).not.toBe(pending.tone);
      for (const language of LANGUAGES) {
        expect(labelOf(status, language), `${status} in ${language}`).not.toBe(labelOf('new', language));
      }
    },
  );

  it('says what each status means in Uzbek, Russian and English', () => {
    expect(labelsIn('uz')).toEqual({
      new: 'Kutilmoqda',
      accepted: 'Qabul qilindi',
      preparing: 'Tayyorlanmoqda',
      ready: 'Tayyor',
      courier_assigned: 'Kuryer biriktirildi',
      on_the_way: "Yo'lda",
      delivery: "Yo'lda",
      delivered: 'Yetkazildi',
      canceled: 'Bekor qilindi',
      rejected: 'Rad etildi',
      refunded: 'Qaytarildi',
    });
    expect(labelsIn('ru')).toEqual({
      new: 'Ожидание',
      accepted: 'Принят',
      preparing: 'Готовится',
      ready: 'Готов',
      courier_assigned: 'Курьер назначен',
      on_the_way: 'В пути',
      delivery: 'В пути',
      delivered: 'Доставлен',
      canceled: 'Отменен',
      rejected: 'Отклонен',
      refunded: 'Возвращен',
    });
    expect(labelsIn('en')).toEqual({
      new: 'Pending',
      accepted: 'Accepted',
      preparing: 'Preparing',
      ready: 'Ready',
      courier_assigned: 'Courier assigned',
      on_the_way: 'On the way',
      delivery: 'On the way',
      delivered: 'Delivered',
      canceled: 'Cancelled',
      rejected: 'Rejected',
      refunded: 'Refunded',
    });
  });
});

describe('getOrderStatusLook: a status the app does not know', () => {
  it('is neutral and has no label key, so the screen can show the status as it came', () => {
    const look = getOrderStatusLook('awaiting_pickup');

    expect(look.labelKey).toBeNull();
    expect(look.staffLabelKey).toBeNull();
    expect(look.tone).toBe('neutral');
    for (const status of STATUSES) {
      expect(look.icon).not.toBe(getOrderStatusLook(status).icon);
    }
  });

  it('does not mistake an inherited object property for a status', () => {
    for (const name of ['constructor', 'toString', '__proto__', 'hasOwnProperty', '']) {
      expect(getOrderStatusLook(name).labelKey, name).toBeNull();
    }
  });
});
