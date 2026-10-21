import { createContext, useContext, type ReactNode } from 'react';
import type { StaffApi } from '../api/client';

// The hooks read the API from here. An app mounts <StaffApiProvider> once (the partner web, whose API address comes
// from the server at runtime), or registers one default with setDefaultStaffApi() (the Mini App, which has a single
// API for its whole life; its tests need no wrapper).
const StaffApiContext = createContext<StaffApi | null>(null);

let defaultApi: StaffApi | null = null;

export function setDefaultStaffApi(api: StaffApi | null): void {
  defaultApi = api;
}

export function StaffApiProvider({ api, children }: { api: StaffApi; children: ReactNode }) {
  return <StaffApiContext.Provider value={api}>{children}</StaffApiContext.Provider>;
}

export function useStaffApi(): StaffApi {
  const api = useContext(StaffApiContext) ?? defaultApi;
  if (!api) throw new Error('@rizqim/staff: no API. Wrap the app in <StaffApiProvider api={...}> or call setDefaultStaffApi().');
  return api;
}
