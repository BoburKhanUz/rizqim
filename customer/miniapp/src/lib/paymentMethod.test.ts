import { describe, expect, it } from 'vitest';
import { getPaymentMethodLabel, getPaymentMethodLabelKey } from './paymentMethod';
import { useLanguageStore } from '../store/languageStore';

describe('payment method names', () => {
  it('translates the methods the API sends, whatever their case', () => {
    expect(getPaymentMethodLabelKey('CASH')).toBe('paymentMethod_cash');
    expect(getPaymentMethodLabelKey('click')).toBe('paymentMethod_click');
    expect(getPaymentMethodLabelKey('terminal')).toBe('paymentMethod_card');
    useLanguageStore.setState({ language: 'uz' });
    expect(getPaymentMethodLabel('CASH', useLanguageStore.getState().t)).toBe('Naqd pul');
    useLanguageStore.setState({ language: 'ru' });
    expect(getPaymentMethodLabel('cash', useLanguageStore.getState().t)).toBe('Наличные');
    useLanguageStore.setState({ language: 'uz' });
  });

  it('shows an unknown method as it was sent', () => {
    expect(getPaymentMethodLabelKey('bitcoin')).toBeNull();
    expect(getPaymentMethodLabel('bitcoin', useLanguageStore.getState().t)).toBe('bitcoin');
    expect(getPaymentMethodLabel(null, useLanguageStore.getState().t)).toBe('');
  });
});
