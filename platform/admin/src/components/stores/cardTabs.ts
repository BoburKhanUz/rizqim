// The tabs of a store's 360° card. Kept out of the client component so the page (a server component) can read `?tab=` with it.
export type CardTab = 'overview' | 'branches' | 'devices' | 'finance' | 'orders' | 'complaints' | 'history' | 'crm';

export const CARD_TABS: readonly CardTab[] = ['overview', 'branches', 'devices', 'finance', 'orders', 'complaints', 'history', 'crm'];

export const isCardTab = (value: unknown): value is CardTab => typeof value === 'string' && (CARD_TABS as readonly string[]).includes(value);
