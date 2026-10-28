import { billingRefusalOf, planName } from '@rizqim/staff';
import { isMessageKey, type Translate } from '@/i18n';

// The name of a gated feature in the panel's language ("Bron"); the code itself when there is no text for it
export function featureLabel(t: Translate, feature: string | null | undefined): string | null {
  if (!feature) return null;
  const key = `billing_f_${feature}`;
  return isMessageKey(key) ? t(key as 'billing_f_bookings') : null;
}

// What a 403 plan_feature_missing / subscription_locked / device_limit says, in plain words in the panel's language. The server
// words the same refusals in Uzbek (docs/BILLING.md); these texts are the same message for ru and en and carry the plan name.
// undefined for any other error.
export function billingRefusalText(err: unknown, t: Translate): string | undefined {
  const refusal = billingRefusalOf(err);
  if (!refusal) return undefined;
  switch (refusal.reason) {
    case 'plan_feature_missing': {
      const feature = featureLabel(t, refusal.feature);
      const plan = refusal.requiredPlanName ?? planName(refusal.requiredPlan);
      if (!feature) return t('billing_err_featureUnknown');
      return plan ? t('billing_err_feature', { feature, plan }) : t('billing_err_featureNoPlan', { feature });
    }
    case 'subscription_locked':
      return t('billing_err_locked');
    case 'device_limit':
      return t('billing_err_device');
  }
}

// "Asosiy tarifda bor" for a feature, or "tarifingizda yo'q" when no plan has it
export function inPlanText(t: Translate, plan: string | null): string {
  return plan ? t('billing_inPlan', { plan }) : t('billing_notInPlan');
}

