'use client';

import { Check, Undo2, X } from 'lucide-react';
import { Button, Card, Pill, Section } from '@rizqim/ui';
import type { OrderCard, StoreRefundReview } from '@/lib/api/orders';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { exactMoney } from './ordersModel';

export type StoreRefundAction = 'propose' | 'confirm' | 'dismiss';

const STATUS: Record<StoreRefundReview['status'], { label: 'orders_storeRefundPending' | 'orders_storeRefundApproved' | 'orders_storeRefundDismissed'; tone: 'warning' | 'success' | 'neutral' }> = {
  pending: { label: 'orders_storeRefundPending', tone: 'warning' },
  approved: { label: 'orders_storeRefundApproved', tone: 'success' },
  dismissed: { label: 'orders_storeRefundDismissed', tone: 'neutral' },
};

export const providerName = (provider: string | null) => (provider ? provider.charAt(0).toUpperCase() + provider.slice(1) : '—');

// The card names members by id only; their names come from the card's own audit and history entries (the decision is audited)
function memberNames(order: OrderCard): Map<number, string> {
  const names = new Map<number, string>();
  for (const row of order.history) if (row.actor) names.set(row.actor.id, row.actor.name);
  for (const entry of order.audit) if (entry.actor) names.set(entry.actor.id, entry.actor.name);
  return names;
}

// Wave P: a refund the store made from its own Payme or (owner decision 2) Paylov cabinet on a delivered order (docs/API.md "Ovqatxona kassasidan
// qaytarish"). The customer already has the money back; RIZQIM keeps its commission until the team decides: approving takes
// two members (a proposal, then a different member's confirmation), dismissing one. The buttons follow `actions`.
export function StoreRefundReviewSection({ order, onAction }: { order: OrderCard; onAction: (action: StoreRefundAction) => void }) {
  const t = useT();
  const latest = order.store_refund_review;
  if (!latest || order.store_refund_reviews.length === 0) return null;

  const names = memberNames(order);
  const member = (id: number | null) => (id === null ? '—' : (names.get(id) ?? t('orders_storeRefundMember', { id })));
  const { can_decide_store_refund: canDecide, can_approve_store_refund: canApprove } = order.actions;
  const pending = latest.status === 'pending';
  const proposed = pending && latest.proposed_by !== null;

  return (
    <Section title={t('orders_storeRefund')}>
      <Card className="flex flex-col gap-3 border-warning-soft-fg/40">
        <p className="text-footnote text-fg">{t('orders_storeRefundExplain', { provider: providerName(latest.provider) })}</p>
        {pending && <p className="text-footnote text-fg-muted">{t('orders_storeRefundHow', { provider: providerName(latest.provider) })}</p>}

        <ul className="flex flex-col gap-3 border-t border-line pt-3">
          {order.store_refund_reviews.map((review, index) => (
            <li key={`${review.payment_uuid}-${review.reported_at ?? index}`} className="flex flex-col gap-1 text-footnote">
              <span className="flex flex-wrap items-center gap-2">
                <span className="font-bold text-fg">{exactMoney(review.amount)}</span>
                <Pill tone={STATUS[review.status]?.tone ?? 'neutral'}>{t(STATUS[review.status]?.label ?? 'orders_storeRefundPending')}</Pill>
              </span>
              <span className="text-caption text-fg-muted">{t('orders_storeRefundReported', { provider: providerName(review.provider), time: when(review.reported_at) })}</span>
              {review.proposed_by !== null && (
                <span className="break-words text-caption text-fg-muted">
                  {t('orders_storeRefundProposedBy', { name: member(review.proposed_by), time: when(review.proposed_at) })}
                  {review.proposal_note ? ` — ${review.proposal_note}` : ''}
                </span>
              )}
              {review.decided_by !== null && (
                <span className="break-words text-caption text-fg-muted">
                  {t('orders_storeRefundDecidedBy', { name: member(review.decided_by), time: when(review.decided_at) })}
                  {review.note ? ` — ${review.note}` : ''}
                </span>
              )}
            </li>
          ))}
        </ul>

        {pending && canDecide && proposed && !canApprove && <p className="text-footnote font-bold text-warning-soft-fg">{t('orders_storeRefundMine')}</p>}
        {pending && canDecide && proposed && canApprove && <p className="text-footnote font-bold text-warning-soft-fg">{t('orders_storeRefundNeedsSecond')}</p>}
        {pending && !canDecide && <p className="text-footnote text-fg-muted">{t('orders_storeRefundViewOnly')}</p>}
        {pending && canDecide && (
          <div role="group" aria-label={t('orders_storeRefund')} className="flex flex-wrap gap-2">
            {canApprove && !proposed && (
              <Button icon={Undo2} onClick={() => onAction('propose')}>
                {t('orders_storeRefundPropose')}
              </Button>
            )}
            {canApprove && proposed && (
              <Button icon={Check} onClick={() => onAction('confirm')}>
                {t('orders_storeRefundConfirm')}
              </Button>
            )}
            <Button variant="secondary" icon={X} onClick={() => onAction('dismiss')}>
              {t('orders_storeRefundDismiss')}
            </Button>
          </div>
        )}
      </Card>
    </Section>
  );
}
