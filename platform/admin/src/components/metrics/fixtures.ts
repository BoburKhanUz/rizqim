import type { Funnel, MetricsOverview } from '@/lib/api/metrics';

// The store set of the backend's AdminMetricsTest (today is 2026-10-28), four months: Jul, Aug, Sep and the Oct in progress.
export function overview(overrides: Partial<MetricsOverview> = {}): MetricsOverview {
  return {
    timezone: 'Asia/Tashkent',
    generated_at: '2026-10-28T12:00:00+05:00',
    months: 4,
    cards: {
      mrr: '831166.67',
      mrr_change: { direction: 'up', amount: '100000.00', percent: '13.7' },
      arr: '9974000.04',
      paying_stores: 3,
      trialing_stores: 1,
      arpa: '277055.56',
      active_stores: 4,
      churn_rate: '50.0',
      churn_month: '2026-09',
      gmv_month: '1500000.00',
      commission_month: '150000.00',
      revenue_complete: false,
    },
    series: [
      { month: '2026-07', partial: false, mrr: '772166.67', paying_stores: 3, trialing_stores: 0, active_stores: 3 },
      { month: '2026-08', partial: false, mrr: '921166.67', paying_stores: 4, trialing_stores: 0, active_stores: 4 },
      { month: '2026-09', partial: false, mrr: '731166.67', paying_stores: 3, trialing_stores: 0, active_stores: 3 },
      { month: '2026-10', partial: true, mrr: '831166.67', paying_stores: 3, trialing_stores: 1, active_stores: 4 },
    ],
    movement: [
      { month: '2026-07', partial: false, opening: '0.00', closing: '772166.67', new: { mrr: '772166.67', stores: 3 }, expansion: { mrr: '0.00', stores: 0 }, contraction: { mrr: '0.00', stores: 0 }, churned: { mrr: '0.00', stores: 0 }, net_new: '772166.67', paying_stores: 3, trialing_stores: 0 },
      { month: '2026-08', partial: false, opening: '772166.67', closing: '921166.67', new: { mrr: '149000.00', stores: 1 }, expansion: { mrr: '0.00', stores: 0 }, contraction: { mrr: '0.00', stores: 0 }, churned: { mrr: '0.00', stores: 0 }, net_new: '149000.00', paying_stores: 4, trialing_stores: 0 },
      { month: '2026-09', partial: false, opening: '921166.67', closing: '731166.67', new: { mrr: '0.00', stores: 0 }, expansion: { mrr: '59000.00', stores: 1 }, contraction: { mrr: '0.00', stores: 0 }, churned: { mrr: '249000.00', stores: 1 }, net_new: '-190000.00', paying_stores: 3, trialing_stores: 0 },
      { month: '2026-10', partial: true, opening: '731166.67', closing: '831166.67', new: { mrr: '0.00', stores: 0 }, expansion: { mrr: '100000.00', stores: 1 }, contraction: { mrr: '0.00', stores: 0 }, churned: { mrr: '0.00', stores: 0 }, net_new: '100000.00', paying_stores: 3, trialing_stores: 1 },
    ],
    churn: [
      { month: '2026-07', partial: false, active_at_start: 3, active_at_end: 3, churned: 0, went_inactive: 0, subscription_ended: 0, rate: '0.0', revenue_rate: null },
      { month: '2026-08', partial: false, active_at_start: 3, active_at_end: 4, churned: 0, went_inactive: 0, subscription_ended: 0, rate: '0.0', revenue_rate: '0.0' },
      { month: '2026-09', partial: false, active_at_start: 4, active_at_end: 3, churned: 2, went_inactive: 1, subscription_ended: 1, rate: '50.0', revenue_rate: '27.0' },
      { month: '2026-10', partial: true, active_at_start: 3, active_at_end: 4, churned: 0, went_inactive: 0, subscription_ended: 0, rate: '0.0', revenue_rate: '0.0' },
    ],
    revenue: [
      { month: '2026-07', partial: false, orders: 0, gmv: '0.00', commission: '0.00', subscription_revenue: '0.00', missing_days: 31, complete: false },
      { month: '2026-08', partial: false, orders: 0, gmv: '0.00', commission: '0.00', subscription_revenue: '0.00', missing_days: 31, complete: false },
      { month: '2026-09', partial: false, orders: 2, gmv: '200000.00', commission: '20000.00', subscription_revenue: '0.00', missing_days: 0, complete: true },
      { month: '2026-10', partial: true, orders: 15, gmv: '1500000.00', commission: '150000.00', subscription_revenue: '149000.00', missing_days: 27, complete: false },
    ],
    ...overrides,
  };
}

export function funnel(overrides: Partial<Funnel> = {}): Funnel {
  return {
    months: 12,
    from: '2025-11',
    applications: 4,
    active: 1,
    rejected: 1,
    open: 2,
    conversion: '25.0',
    median_days_to_active: 14,
    stages: [
      { stage: 'application', label: 'Ariza', reached: 4, share: '100.0', median_days: 2, samples: 3 },
      { stage: 'review', label: 'Tekshiruv', reached: 3, share: '75.0', median_days: 2, samples: 3 },
      { stage: 'menu', label: 'Menyu', reached: 2, share: '50.0', median_days: 1, samples: 1 },
      { stage: 'devices', label: 'Qurilmalar', reached: 1, share: '25.0', median_days: 2.5, samples: 1 },
      { stage: 'training', label: "O'qitish", reached: 1, share: '25.0', median_days: null, samples: 0 },
      { stage: 'trial', label: 'Sinov', reached: 1, share: '25.0', median_days: 5, samples: 1 },
      { stage: 'active', label: 'Faol', reached: 1, share: '25.0', median_days: null, samples: 0 },
    ],
    ...overrides,
  };
}
