import { useQuery } from '@tanstack/react-query';
import { fetchEntitlements, hasFeature, requiredPlanName, type Entitlements, type PlanFeatureKey } from '../api/entitlements';
import { useStaffApi } from './context';
import { staffKeys } from './keys';
import type { QueryExtras } from './orders';

// What the plan lets the store use (P4-21). Read once and kept fresh for a few minutes: paying an invoice opens the locks at
// once on the server, and the panel follows on the next read (focus, the poll below or a reload). No retry: a failed read
// leaves everything open (the server refuses what the plan does not allow anyway).
export function useEntitlements(options: QueryExtras<Entitlements> = {}) {
  const api = useStaffApi();
  return useQuery({
    staleTime: 60_000,
    refetchInterval: 5 * 60_000,
    retry: false,
    ...options,
    queryKey: staffKeys.entitlements,
    queryFn: () => fetchEntitlements(api),
  });
}

export interface FeatureAccess {
  // Is the feature on? True while unknown (loading, no answer): the server is the judge and refuses a write that is not allowed
  allowed: boolean;
  // The cheapest plan that has it ("Asosiy"), when the feature is off
  planName: string | null;
  // The first answer is still on its way
  loading: boolean;
}

export function useFeature(feature: PlanFeatureKey): FeatureAccess {
  const query = useEntitlements();
  const entitlements = query.data;
  const allowed = hasFeature(entitlements, feature);
  return { allowed, planName: entitlements && !allowed ? requiredPlanName(entitlements, feature) : null, loading: query.isPending };
}
