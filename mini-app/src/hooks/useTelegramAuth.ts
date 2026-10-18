import { useEffect, useState } from 'react';
import { getInitData, getTelegramUser } from '../lib/telegram';
import { useAuthStore } from '../store/authStore';
import api from '../services/api';

export const useTelegramAuth = () => {
  const { token, isAuthenticated, setAuth, logout, user: storedUser } = useAuthStore();
  const [isAuthenticating, setIsAuthenticating] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const authenticateWithTelegram = async () => {
      // 1. Check if Telegram SDK is loaded
      if (!window.Telegram || !window.Telegram.WebApp) {
        setError('Telegram SDK not loaded.');
        return;
      }

      // 2. Check if there is a start_param (deep link) which means we MUST process it
      const startParam = window.Telegram?.WebApp?.initDataUnsafe?.start_param;
      
      // 3. Already authenticated (Persist check) - Instant 0-second UI launch!
      // Only bypass if there is NO start_param and stored user telegram_id matches real TG user if inside TG.
      const realTgUser = getTelegramUser();
      const isSessionValid = isAuthenticated && token && (!realTgUser || String(storedUser?.telegram_id) === String(realTgUser.id));

      if (isSessionValid && !startParam) {
        setIsAuthenticating(false);
        // Silently verify user session in the background and refresh local store settings
        api.get('/user').then((res) => {
          if (res.data?.success && res.data?.data) {
            const freshData = res.data.data;
            setAuth(token, freshData.user, freshData.store, freshData.sms_verification_required);
            console.log('Background session refreshed with fresh store settings.');
          }
        }).catch((e) => {
          console.warn('Silent background session verification failed:', e);
          if (e.response?.status === 401) {
            logout();
          }
        });
        return; // Valid session
      }

      // 3. Authenticate with Backend
      let initData = getInitData();
      let user = getTelegramUser();
      
      // Developer Mock Bypass: Only use mock if NO initData is present (not running inside Telegram)
      if (initData) {
        localStorage.removeItem('mock_telegram_id');
        localStorage.removeItem('mock_telegram_username');
        localStorage.removeItem('mock_first_name');
      } else {
        console.log('Using mock credentials override...');
        let mockId = localStorage.getItem('mock_telegram_id');
        const mockUsername = localStorage.getItem('mock_telegram_username');
        const mockFirstName = localStorage.getItem('mock_first_name') || 'Mijoz Test';

        if (!mockId) {
          mockId = String(Math.floor(10000000 + Math.random() * 90000000));
          localStorage.setItem('mock_telegram_id', mockId);
        }
        const mockUser = {
          id: parseInt(mockId),
          first_name: mockFirstName,
          last_name: mockUsername ? '' : `#${mockId.substring(4)}`,
          username: mockUsername || `user_${mockId}`,
          language_code: 'uz'
        };
        initData = `mock_data=true&user=${encodeURIComponent(JSON.stringify(mockUser))}&auth_date=${Math.floor(Date.now() / 1000)}&hash=mock`;
        user = mockUser;
      }
      
      console.log('Telegram InitData:', initData);
      console.log('Telegram InitDataUnsafe:', user);

      try {
        setIsAuthenticating(true);
        setError(null);
        
        // POST /api/v1/auth/telegram
        const botUsername = import.meta.env.VITE_BOT_USERNAME || 'rizqimbot';
        const startParam = window.Telegram?.WebApp?.initDataUnsafe?.start_param || null;
        
        const response = await api.post('/auth/telegram', {
          initData: initData,
          bot_username: botUsername,
          start_param: startParam,
        }, { timeout: 10000 }); // 10 seconds timeout

        if (response.data?.success && response.data?.data) {
          const authData = response.data.data;
          setAuth(authData.token, authData.user, authData.store, authData.sms_verification_required);
          console.log('Successfully Authenticated via Telegram!');
        } else {
          setError('Authentication failed: Invalid response format.');
        }
      } catch (err: any) {
        console.error('Auth Error:', err);
        if (err.code === 'ECONNABORTED') {
          setError('Connection timeout. Please check your internet connection.');
        } else {
          setError(err.response?.data?.message || 'Failed to authenticate with backend.');
        }
      } finally {
        setIsAuthenticating(false);
      }
    };

    authenticateWithTelegram();
  }, []);

  return { isAuthenticating, error, isAuthenticated, token, user: storedUser };
};
