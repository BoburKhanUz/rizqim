// Where the e2e run listens: the production build of the control center (next start) and the fake backend it talks to.
// Uncommon ports, so a dev server on 3100 and the partner web's e2e (13100/14100) do not get in the way;
// E2E_WEB_PORT / E2E_FAKE_PORT move them.
export const WEB_PORT = Number(process.env.E2E_WEB_PORT) || 13200
export const FAKE_PORT = Number(process.env.E2E_FAKE_PORT) || 14200
export const WEB_URL = `http://127.0.0.1:${WEB_PORT}`
export const FAKE_URL = `http://127.0.0.1:${FAKE_PORT}`
