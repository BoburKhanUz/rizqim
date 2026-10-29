// The tabs of Tizim. Kept out of the client component so the page (a server component) can read `?tab=` with it.
export type SystemTab = 'settings' | 'payments' | 'locations' | 'flags';

export const SYSTEM_TABS: readonly SystemTab[] = ['settings', 'payments', 'locations', 'flags'];

export const isSystemTab = (value: unknown): value is SystemTab => typeof value === 'string' && (SYSTEM_TABS as readonly string[]).includes(value);
