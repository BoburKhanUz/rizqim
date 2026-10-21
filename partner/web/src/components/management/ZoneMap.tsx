'use client';

import dynamic from 'next/dynamic';

// Leaflet touches `window` as soon as it is imported, so the map is loaded in the browser only (a Next server render would
// crash on it). Until it arrives a grey block of the same size holds the place.
export const ZoneMap = dynamic(() => import('./ZoneMapCanvas'), {
  ssr: false,
  loading: () => <div role="status" aria-busy="true" className="h-[520px] animate-pulse rounded-card border border-line bg-surface-2" />,
});
