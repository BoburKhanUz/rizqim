import { QueryClient } from '@tanstack/react-query';

// The app's one React Query cache (in memory only, never persisted). Its own module so that dropping a stored session
// (lib/session.ts) can empty it too.
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      staleTime: 1000 * 60 * 5,
    },
  },
});
