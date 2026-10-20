import { describe, expect, it } from 'vitest';
import { getNotificationLook } from './notificationLook';

describe('getNotificationLook', () => {
  it('reads the kind of a notification behind the audience prefix of the API', () => {
    expect(getNotificationLook('telegram_customer_accepted').tone).toBe('success');
    expect(getNotificationLook('telegram_customer_canceled').tone).toBe('danger');
    expect(getNotificationLook('telegram_customer_on_the_way').tone).toBe('info');
    expect(getNotificationLook('telegram_manager_new_order').tone).toBe('brand');
    expect(getNotificationLook('telegram_manager_courier_delay_warning').tone).toBe('warning');
  });

  it('knows the types that carry no prefix', () => {
    expect(getNotificationLook('complaint_reply').tone).toBe('info');
    expect(getNotificationLook('store_payment_required').tone).toBe('danger');
  });

  it('is neutral for a type it does not know, including names that exist on Object', () => {
    expect(getNotificationLook('something_new').tone).toBe('neutral');
    expect(getNotificationLook('constructor').tone).toBe('neutral');
  });
});
