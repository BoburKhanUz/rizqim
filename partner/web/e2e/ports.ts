// Where the e2e run listens: the production build of the panel (next start) and the fake backend it talks to. Uncommon
// ports, so a dev server on 3000/8000 does not get in the way; E2E_WEB_PORT / E2E_FAKE_PORT move them.
export const WEB_PORT = Number(process.env.E2E_WEB_PORT) || 13100
export const FAKE_PORT = Number(process.env.E2E_FAKE_PORT) || 14100
// Reverb is answered by the page's own WebSocket mock (e2e/fake/reverb.ts), so this port has no server behind it
export const REVERB_PORT = FAKE_PORT + 1
export const WEB_URL = `http://127.0.0.1:${WEB_PORT}`
export const FAKE_URL = `http://127.0.0.1:${FAKE_PORT}`
