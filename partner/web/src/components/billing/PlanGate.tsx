'use client';

import type { ReactNode } from 'react';
import Link from 'next/link';
import { Lock } from 'lucide-react';
import { Button, cx, Pill, type ButtonProps } from '@rizqim/ui';
import { useFeature, type GatedFeature } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { featureLabel, inPlanText } from './refusal';

// The "Asosiy tarifda bor" locks (P4-21). The plan decides in the backend (the entitlements); the panel only hides and explains
// what the plan does not have, instead of letting the person walk into a 403:
//   <PlanLockPill />      the small label next to a locked control
//   <PlanButton />        a button that is disabled and says so when the feature is not in the plan
//   <PlanNotice />        a notice above a page whose "add" actions are locked (what exists stays usable)
//   <PlanGate />          a whole section that the plan does not have: the lock card replaces it
// While the entitlements are loading (or could not be read) everything stays open; the server refuses a write it does not allow.

export function PlanLockPill({ plan, className }: { plan: string | null; className?: string }) {
  const { t } = useI18n();
  return (
    <Pill tone="info" icon={Lock} className={className}>
      {inPlanText(t, plan)}
    </Pill>
  );
}

// A button for a gated action. Disabled with the lock label beside it when the feature is not in the plan.
export function PlanButton({ feature, children, className, ...props }: ButtonProps & { feature: GatedFeature }) {
  const { t } = useI18n();
  const access = useFeature(feature);
  if (access.allowed) {
    return (
      <Button className={className} {...props}>
        {children}
      </Button>
    );
  }
  return (
    <span className="inline-flex flex-wrap items-center gap-2">
      <Button {...props} className={className} disabled aria-disabled="true" title={t('billing_lockedHint')}>
        {children}
      </Button>
      <PlanLockPill plan={access.planName} />
    </span>
  );
}

function LockCard({ feature, plan, compact }: { feature: GatedFeature; plan: string | null; compact?: boolean }) {
  const { t } = useI18n();
  const label = featureLabel(t, feature) ?? feature;
  return (
    <section aria-label={label} className={cx('flex items-start gap-3 rounded-card border border-line bg-info-soft px-4 py-3 text-info-soft-fg', compact ? '' : 'py-5')}>
      <Lock aria-hidden="true" className="mt-0.5 size-5 shrink-0" />
      <div className="flex min-w-0 flex-col gap-1">
        <p className="text-body font-bold">{plan ? t('billing_gateTitle', { feature: label, plan }) : t('billing_gateTitleNoPlan', { feature: label })}</p>
        <p className="text-footnote">{t('billing_gateText')}</p>
        <Link href="/billing" className="w-fit text-footnote font-bold underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-brand">
          {t('billing_gateLink')}
        </Link>
      </div>
    </section>
  );
}

// A notice above a page: nothing when the feature is in the plan
export function PlanNotice({ feature, className }: { feature: GatedFeature; className?: string }) {
  const access = useFeature(feature);
  if (access.allowed) return null;
  return (
    <div className={cx('mb-4', className)}>
      <LockCard feature={feature} plan={access.planName} compact />
    </div>
  );
}

// A section the plan does not have: the lock card in place of `children`
export function PlanGate({ feature, children }: { feature: GatedFeature; children: ReactNode }) {
  const access = useFeature(feature);
  if (access.allowed) return <>{children}</>;
  return <LockCard feature={feature} plan={access.planName} />;
}
