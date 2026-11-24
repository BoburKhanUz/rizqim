// Runs before every other setup file and before any app module is loaded: the tests are a dev build outside Telegram,
// so the per-user storage (lib/userStorage.ts) is the dev server's mock account. It is the account of the factories'
// default user (makeUser), so a session seeded with makeUser() is the current Telegram user's own.
import { TEST_TELEGRAM_ID } from './telegramId';

localStorage.setItem('mock_telegram_id', TEST_TELEGRAM_ID);
