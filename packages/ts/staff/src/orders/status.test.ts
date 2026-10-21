import { getOrderStatusLook } from '@rizqim/ui';
import { describe, expect, it } from 'vitest';
import { STAFF_STATUS_LABELS, statusLabel } from '../i18n/messages';
import type { OrderStatus } from '../types';
import {
  CANCELABLE_STATUSES,
  FINISHED_STATUSES,
  GROUP_STATUSES,
  getOrderActions,
  isCancellation,
  isFinishedStatus,
  isOpenStatus,
  isOrderStatus,
  OPEN_STATUSES,
  ORDER_STATUSES,
  STATUS_GROUPS,
  STATUS_TONE,
  statusGroup,
  statusTone,
} from './status';

describe('the status list', () => {
  it('has every value of App\\Enums\\OrderStatus once', () => {
    expect([...ORDER_STATUSES].sort()).toEqual(
      ['accepted', 'canceled', 'courier_assigned', 'delivered', 'delivery', 'new', 'on_the_way', 'preparing', 'ready', 'refunded', 'rejected'],
    );
  });

  it('splits into open and finished, with nothing in both and nothing left over', () => {
    expect([...OPEN_STATUSES, ...FINISHED_STATUSES].sort()).toEqual([...ORDER_STATUSES].sort());
    expect(isOpenStatus('preparing')).toBe(true);
    expect(isOpenStatus('delivered')).toBe(false);
    expect(isFinishedStatus('refunded')).toBe(true);
    expect(isFinishedStatus('new')).toBe(false);
    // a status the package has never seen is neither
    expect(isOpenStatus('teleported') || isFinishedStatus('teleported')).toBe(false);
    expect(isOrderStatus('teleported')).toBe(false);
  });

  it('knows which statuses staff can still cancel from the kitchen side', () => {
    expect(CANCELABLE_STATUSES).toEqual(['accepted', 'preparing', 'ready']);
    expect(isCancellation('canceled')).toBe(true);
    expect(isCancellation('rejected')).toBe(true);
    expect(isCancellation('delivered')).toBe(false);
  });
});

describe('colour groups', () => {
  it('gives each status the same tone @rizqim/ui draws its badge with', () => {
    for (const status of ORDER_STATUSES) {
      expect(STATUS_TONE[status], status).toBe(getOrderStatusLook(status).tone);
    }
  });

  it('is neutral for a status it does not know', () => {
    expect(statusTone('teleported')).toBe('neutral');
    expect(getOrderStatusLook('teleported').tone).toBe('neutral');
  });

  it('puts the statuses in the groups of a live board, each in exactly one', () => {
    const grouped = STATUS_GROUPS.flatMap((group) => GROUP_STATUSES[group]);
    expect([...grouped].sort()).toEqual([...ORDER_STATUSES].sort());
    expect(statusGroup('accepted')).toBe('preparing');
    expect(statusGroup('on_the_way')).toBe('courier');
    expect(statusGroup('delivery')).toBe('courier');
    expect(statusGroup('refunded')).toBe('canceled');
    expect(statusGroup('teleported')).toBeNull();
  });
});

describe('the staff labels', () => {
  it.each(['uz', 'ru', 'en'] as const)('names every status in %s', (language) => {
    for (const status of ORDER_STATUSES) {
      expect(statusLabel(status, language), `${status} in ${language}`).toBeTruthy();
    }
  });

  it('matches what the Mini App printed before the package (staffStatus_*)', () => {
    expect(statusLabel('new', 'uz')).toBe('Yangi');
    expect(statusLabel('courier_assigned', 'uz')).toBe('Kuryer kutilmoqda');
    expect(statusLabel('new', 'ru')).toBe('Новый');
    expect(statusLabel('canceled', 'en')).toBe('Cancelled');
    expect(statusLabel('delivery', 'uz')).toBe(statusLabel('on_the_way', 'uz'));
  });

  it('tells the statuses apart: only the two names of one stage read the same', () => {
    for (const language of ['uz', 'ru', 'en'] as const) {
      const byLabel = new Map<string, OrderStatus[]>();
      for (const status of ORDER_STATUSES) {
        const label = STAFF_STATUS_LABELS[status][language];
        byLabel.set(label, [...(byLabel.get(label) ?? []), status]);
      }
      expect([...byLabel.values()].filter((group) => group.length > 1)).toEqual([['on_the_way', 'delivery']]);
    }
  });

  it('shows a status it does not know as the API sent it', () => {
    expect(statusLabel('teleported', 'uz')).toBe('teleported');
  });
});

describe('the next actions of a role', () => {
  const ids = (order: Parameters<typeof getOrderActions>[0], role: string) => getOrderActions(order, role).map((a) => a.id);

  it('walks a delivery order for the manager from accept to the confirmed delivery', () => {
    expect(ids({ status: 'new', order_type: 'delivery' }, 'manager')).toEqual(['accept', 'reject']);
    expect(ids({ status: 'accepted', order_type: 'delivery' }, 'manager')).toEqual(['start_preparing', 'cancel']);
    expect(ids({ status: 'preparing', order_type: 'delivery' }, 'manager')).toEqual(['mark_ready', 'cancel']);
    expect(ids({ status: 'ready', order_type: 'delivery' }, 'manager')).toEqual(['assign_courier', 'cancel']);
    expect(ids({ status: 'courier_assigned', order_type: 'delivery' }, 'manager')).toEqual(['pick_up']);
    expect(ids({ status: 'on_the_way', order_type: 'delivery' }, 'manager')).toEqual(['confirm_delivery']);
    expect(ids({ status: 'delivery', order_type: 'delivery' }, 'manager')).toEqual(['confirm_delivery']);
  });

  it('hands a pickup or dine-in order over at the counter instead of assigning a courier', () => {
    expect(ids({ status: 'ready', order_type: 'pickup' }, 'manager')).toEqual(['hand_over', 'cancel']);
    expect(ids({ status: 'ready', order_type: 'dine_in' }, 'owner')).toEqual(['hand_over', 'cancel']);
  });

  it('says what each action needs and where it goes', () => {
    const [accept, reject] = getOrderActions({ status: 'new', order_type: 'delivery' }, 'manager');
    expect(accept).toMatchObject({ to: 'accepted', needsReason: false, needsCourier: false, via: 'status', destructive: false });
    expect(reject).toMatchObject({ to: 'rejected', needsReason: true, destructive: true });
    const [assign, cancel] = getOrderActions({ status: 'ready', order_type: 'delivery' }, 'manager');
    expect(assign).toMatchObject({ to: 'courier_assigned', needsCourier: true, via: 'assign-courier' });
    expect(cancel).toMatchObject({ to: 'canceled', needsReason: true, destructive: true });
  });

  it('gives the owner the manager buttons', () => {
    expect(ids({ status: 'accepted', order_type: 'delivery' }, 'owner')).toEqual(ids({ status: 'accepted', order_type: 'delivery' }, 'manager'));
  });

  it('lets a courier pick up and then complete his own delivery, through /complete', () => {
    expect(ids({ status: 'courier_assigned', order_type: 'delivery' }, 'courier')).toEqual(['pick_up']);
    const [done] = getOrderActions({ status: 'on_the_way', order_type: 'delivery' }, 'courier');
    expect(done).toMatchObject({ id: 'confirm_delivery', to: 'delivered', via: 'complete' });
    expect(ids({ status: 'ready', order_type: 'delivery' }, 'courier')).toEqual([]);
  });

  it('offers nothing on a finished order or to a role it has no screen for', () => {
    for (const status of FINISHED_STATUSES) expect(ids({ status, order_type: 'delivery' }, 'manager'), status).toEqual([]);
    expect(ids({ status: 'new', order_type: 'delivery' }, 'customer')).toEqual([]);
    expect(ids({ status: 'new', order_type: 'delivery' }, 'cook')).toEqual([]);
  });

  it('moves only to statuses the order statuses table knows', () => {
    for (const status of ORDER_STATUSES) {
      for (const type of ['delivery', 'pickup', 'dine_in'] as const) {
        for (const role of ['manager', 'courier']) {
          for (const action of getOrderActions({ status, order_type: type }, role)) {
            expect(isOrderStatus(action.to)).toBe(true);
          }
        }
      }
    }
  });
});
