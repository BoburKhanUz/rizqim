import axios from 'axios';

// What every client of the API sends: the base URL and the JSON headers
export const clientConfig = {
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Bypass-Tunnel-Reminder': 'true',
  },
};

// The plain client the Telegram sign-in (POST /auth/telegram, lib/telegramSignIn.ts) goes out on: it has no
// interceptors, so a sign-in can never recurse into the 401 handling of services/api.ts, nor sign anyone out there.
export const authClient = axios.create(clientConfig);
