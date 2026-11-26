import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { userPersistStorage } from '../lib/userStorage';

// "Click = consent" (owner decision, docs/legal/README.md): the first launch shows a customer, once, that using the app
// accepts the Terms of Use and the Privacy Policy (components/ConsentNotice.tsx). Whether it was shown is remembered per
// Telegram user ("consent-storage:<telegram id>", lib/userStorage.ts), never device-wide: another account on the same
// phone sees it on its own first launch. Nothing is sent to the server; there is no opt-out (owner decision).
interface ConsentState {
  noticeSeen: boolean;
  markNoticeSeen: () => void;
}

export const useConsentStore = create<ConsentState>()(
  persist(
    (set) => ({
      noticeSeen: false,
      markNoticeSeen: () => set({ noticeSeen: true }),
    }),
    {
      name: 'consent-storage', // per Telegram user (lib/userStorage.ts)
      storage: userPersistStorage<Pick<ConsentState, 'noticeSeen'>>(),
      partialize: (state) => ({ noticeSeen: state.noticeSeen }),
    },
  ),
);
