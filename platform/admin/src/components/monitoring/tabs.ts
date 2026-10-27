// The tabs of Monitoring. Kept out of the client component so the page (a server component) can read `?tab=` with it.
export type MonitoringTab = 'status' | 'alerts' | 'jobs' | 'rules';

export const MONITORING_TABS: readonly MonitoringTab[] = ['status', 'alerts', 'jobs', 'rules'];

export const isTab = (value: unknown): value is MonitoringTab => typeof value === 'string' && (MONITORING_TABS as readonly string[]).includes(value);
