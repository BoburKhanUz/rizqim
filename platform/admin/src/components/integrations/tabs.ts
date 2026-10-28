// The tabs of Integratsiyalar. Kept out of the client component so the page (a server component) can read `?tab=` with it.
export type IntegrationsTab = 'connections' | 'events' | 'menu' | 'stuck';

export const INTEGRATIONS_TABS: readonly IntegrationsTab[] = ['connections', 'events', 'menu', 'stuck'];

export const isIntegrationsTab = (value: unknown): value is IntegrationsTab => typeof value === 'string' && (INTEGRATIONS_TABS as readonly string[]).includes(value);
