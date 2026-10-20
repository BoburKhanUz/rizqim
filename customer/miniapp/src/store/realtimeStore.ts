import { create } from 'zustand';

interface RealtimeState {
  // The socket is connected and subscribed to the user's order channel, so order changes arrive as events.
  connected: boolean;
  setConnected: (connected: boolean) => void;
}

// Not persisted: it describes the socket of this page load only.
export const useRealtimeStore = create<RealtimeState>()((set) => ({
  connected: false,
  setConnected: (connected) => set((state) => (state.connected === connected ? state : { connected })),
}));
