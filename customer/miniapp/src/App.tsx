import { Suspense, useEffect, useMemo, useState } from 'react';
import { BrowserRouter, Routes, Route, useLocation, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { initTelegramApp } from './lib/telegram';
import { startTelegramChrome } from './lib/twa';
import { useTelegramAuth } from './hooks/useTelegramAuth';
import { useRealtimeOrders } from './hooks/useRealtimeOrders';
import { useLanguageStore } from './store/languageStore';
import { useAuthStore } from './store/authStore';
import { RotateCw, Send, WifiOff } from 'lucide-react';
import api from './services/api';
import PhoneVerificationModal from './components/PhoneVerificationModal';
import { FullScreenLoader, ErrorFallback, RouteErrorBoundary, RouteFallback } from './components/Feedback';
import { lazyWithRetry } from './lib/lazyWithRetry';
import { getRoleHomePath, isCustomerRole } from './lib/roles';
import { useTelegramBackButton } from './hooks/useTelegramBackButton';
import { BottomNav } from './components/navigation/BottomNav';
import { FloatingCartBar } from './components/navigation/FloatingCartBar';
import { getNavItems, getRootPaths } from './components/navigation/navItems';
import { BottomDock } from './components/ui/BottomDock';
import { Button, buttonClasses } from '@rizqim/ui';
import { DialogHost } from './components/ui/DialogHost';
import { MainButtonHost } from './components/ui/MainButtonHost';
import type { User } from './types';

// The customer's landing page is part of the entry bundle; every other page is its own chunk that is
// downloaded when its route is first visited (see lazyWithRetry), so a courier never downloads the
// owner screens and a customer never downloads the staff ones.
import Home from './pages/Home';

const loadStoreDetail = () => import('./pages/StoreDetail');
const loadCategoryDetail = () => import('./pages/CategoryDetail');
const loadStores = () => import('./pages/Stores');
const loadOrders = () => import('./pages/Orders');

const StoreDetail = lazyWithRetry(loadStoreDetail);
const CategoryDetail = lazyWithRetry(loadCategoryDetail);
const Stores = lazyWithRetry(loadStores);
const Orders = lazyWithRetry(loadOrders);
const Cart = lazyWithRetry(() => import('./pages/Cart'));
const CustomerProfile = lazyWithRetry(() => import('./pages/CustomerProfile'));
const Success = lazyWithRetry(() => import('./pages/Success'));
const Profile = lazyWithRetry(() => import('./pages/Profile'));
const Complaints = lazyWithRetry(() => import('./pages/Complaints'));
const Notifications = lazyWithRetry(() => import('./pages/Notifications'));
const CourierDashboard = lazyWithRetry(() => import('./pages/CourierDashboard'));
const ManagerDashboard = lazyWithRetry(() => import('./pages/ManagerDashboard'));
const StaffOrderDetail = lazyWithRetry(() => import('./pages/StaffOrderDetail'));
const AdminAnalytics = lazyWithRetry(() => import('./pages/AdminAnalytics'));
const AdminReports = lazyWithRetry(() => import('./pages/AdminReports'));
const StopListBatches = lazyWithRetry(() => import('./pages/StopListBatches'));
const OwnerBranches = lazyWithRetry(() => import('./pages/OwnerBranches'));
const OwnerMenuManagement = lazyWithRetry(() => import('./pages/OwnerMenuManagement'));
const OwnerProductForm = lazyWithRetry(() => import('./pages/OwnerProductForm'));
const OwnerEmployees = lazyWithRetry(() => import('./pages/OwnerEmployees'));
const OwnerPayments = lazyWithRetry(() => import('./pages/OwnerPayments'));
const OwnerSettings = lazyWithRetry(() => import('./pages/OwnerSettings'));
const ShiftSummary = lazyWithRetry(() => import('./pages/ShiftSummary'));
const Debtors = lazyWithRetry(() => import('./pages/Debtors'));

// Other modules (including pages owned by other people) still import these from here.
export { FullScreenLoader, ErrorFallback };

const isDebugMode = import.meta.env.VITE_APP_DEBUG === 'true';

// Debugger (only visible if VITE_APP_DEBUG=true)
interface AuthDebuggerProps {
  error: string | null;
  token: string | null;
  user: User | null;
  isAuthenticated: boolean;
}

const AuthDebugger = ({ error, token, user, isAuthenticated }: AuthDebuggerProps) => {
  if (!isDebugMode) return null;
  return (
    <div className="m-4 rounded-card border border-line bg-surface p-4 text-left text-caption">
      <h2 className="mb-2 font-bold">🛠 Debug Mode (VITE_APP_DEBUG=true)</h2>
      {error && <p className="mb-2 text-danger-soft-fg">Error: {error}</p>}
      <pre className="overflow-x-auto">
        {JSON.stringify({ isAuthenticated, user, token: token ? 'Bearer ******' : null }, null, 2)}
      </pre>
    </div>
  );
};

// Dynamic Home Route based on authenticated user's role
function HomeRoute() {
  const { user } = useAuthStore();

  const roleHome = getRoleHomePath(user?.role);
  if (roleHome) {
    return <Navigate to={roleHome} replace />;
  }
  // SaaS Magic feature has been disabled as requested by the user,
  // so that clicking Catalog always goes to the global feed.
  
  return <Home />;
}

// Hide the navigation on these screens (the order confirmation has its own way out)
const NAV_HIDDEN_PATHS = ['/success'];

// Screens where the "view cart" bar is not shown: the cart itself, the customer's own pages, every staff screen
const CART_BAR_HIDDEN_PREFIXES = ['/cart', '/success', '/orders', '/profile', '/complaints', '/notifications'];
const isCartBarHidden = (pathname: string) =>
  CART_BAR_HIDDEN_PREFIXES.some((prefix) => pathname.startsWith(prefix)) ||
  pathname.includes('/admin') ||
  pathname === '/courier' ||
  pathname === '/manager';

// What is fixed at the bottom, for every role: the in-page main button, the cart bar (shop screens) and the tabs.
function AppDock() {
  const { pathname } = useLocation();
  const { user } = useAuthStore();
  const showNav = !NAV_HIDDEN_PATHS.includes(pathname);
  const showCartBar = isCustomerRole(user?.role) && !isCartBarHidden(pathname);
  return (
    <BottomDock withNav={showNav}>
      <MainButtonHost />
      {showCartBar && <FloatingCartBar />}
      {showNav && <BottomNav items={getNavItems(user?.role)} />}
    </BottomDock>
  );
}

// Asks users that have no phone number yet for one (SMS verified when the server requires it)
function PhonePromptModal() {
  const { user, updateUser, smsVerificationRequired } = useAuthStore();

  // If no authenticated user, or user already has a phone number set, don't show the modal
  if (!user || user.phone) return null;

  return (
    <PhoneVerificationModal
      onVerified={(fullPhone) => updateUser({ phone: fullPhone })}
      saveWithoutVerification={smsVerificationRequired ? undefined : async (fullPhone) => {
        // Direct save without SMS if not required
        await api.post('/user/profile', {
          name: user.name,
          phone: fullPhone,
          address: user.address || ''
        });
        updateUser({ phone: fullPhone });
      }}
    />
  );
}

// Gorgeous Premium Blocker for Direct Web Visits
export const OnlyTelegramBlocker = () => {
  const botUsername = import.meta.env.VITE_BOT_USERNAME || 'rizqimbot';
  const botLink = `https://t.me/${botUsername}`;

  // A plain browser: the app only works inside Telegram (it signs in with Telegram's launch data)
  return (
    <div className="fixed inset-0 z-50 flex flex-col items-center justify-center bg-page p-6 text-center">
      <div className="flex w-full max-w-sm flex-col items-center gap-5 rounded-sheet border border-line bg-surface p-8 shadow-xl">
        <span className="flex size-16 items-center justify-center rounded-full bg-brand text-on-brand">
          <Send aria-hidden="true" className="size-8" />
        </span>
        <div className="flex flex-col gap-2">
          <h2 className="text-title font-bold text-fg">Telegram Mini App</h2>
          <p className="text-body text-fg-muted">
            Rizqim faqat Telegram ichida ishlaydi. Ilovani botimiz orqali oching.
          </p>
        </div>
        <a href={botLink} target="_blank" rel="noopener noreferrer" className={buttonClasses({ size: 'lg', fullWidth: true })}>
          Bot orqali ochish
        </a>
      </div>
    </div>
  );
};

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      staleTime: 1000 * 60 * 5,
    },
  },
});

// Customers are likely to open these next from the catalog: fetch them once the app is idle so the tap is instant.
function usePrefetchCustomerPages(enabled: boolean) {
  useEffect(() => {
    if (!enabled) return;
    const timer = setTimeout(() => {
      void loadStoreDetail();
      void loadCategoryDetail();
      void loadStores();
      void loadOrders();
    }, 2500);
    return () => clearTimeout(timer);
  }, [enabled]);
}

function AppContent() {
  const { isAuthenticating, error, isAuthenticated, token, user } = useTelegramAuth();
  const location = useLocation();
  const isCustomer = isCustomerRole(user?.role);

  const { t, language } = useLanguageStore();

  // Screen readers and hyphenation follow the language the user picked
  useEffect(() => {
    document.documentElement.lang = language;
  }, [language]);

  usePrefetchCustomerPages(isCustomer && isAuthenticated);
  useRealtimeOrders(); // order events over the socket, once signed in (a no-op without VITE_REVERB_APP_KEY)
  const rootPaths = useMemo(() => getRootPaths(user?.role), [user?.role]);
  useTelegramBackButton(rootPaths);

  useEffect(() => {
    initTelegramApp();
    // Theme (data-theme + Telegram header/background colours), swipes; follows Telegram's theme changes
    return startTelegramChrome();
  }, []);

  if (isAuthenticating) return <FullScreenLoader message={t('connecting')} />;
  
  if (error && !isAuthenticated) {
    return (
      <>
        <ErrorFallback error={error} />
        <AuthDebugger error={error} token={token} user={user} isAuthenticated={isAuthenticated} />
      </>
    );
  }

  return (
    <div className="relative min-h-screen pb-dock">
      <AuthDebugger error={error} token={token} user={user} isAuthenticated={isAuthenticated} />
      
      {/* Automatically Prompts User for Phone Number on first login if not yet provided */}
      <PhonePromptModal />

      <RouteErrorBoundary resetKey={location.pathname}>
        <Suspense fallback={<RouteFallback />}>
          <Routes>
            <Route path="/" element={<HomeRoute />} />
            <Route path="/store/:id" element={<StoreDetail />} />
            <Route path="/courier" element={<CourierDashboard />} />
            <Route path="/manager" element={<ManagerDashboard />} />
            <Route path="/manager/orders/:uuid" element={<StaffOrderDetail />} />
            <Route path="/admin/analytics" element={<AdminAnalytics />} />
            <Route path="/admin/reports" element={<AdminReports />} />
            <Route path="/admin/stoplist" element={<StopListBatches />} />
            <Route path="/admin/branches" element={<OwnerBranches />} />
            <Route path="/admin/catalog" element={<OwnerMenuManagement />} />
            <Route path="/admin/catalog/create" element={<OwnerProductForm />} />
            <Route path="/admin/catalog/edit/:id" element={<OwnerProductForm />} />
            <Route path="/admin/employees" element={<OwnerEmployees />} />
            <Route path="/admin/payments" element={<OwnerPayments />} />
            <Route path="/admin/shift" element={<ShiftSummary />} />
            <Route path="/admin/debtors" element={<Debtors />} />
            <Route path="/cart" element={<Cart />} />
            <Route path="/orders" element={<Orders />} />
            <Route path="/profile" element={isCustomer ? <CustomerProfile /> : <Profile />} />
            <Route path="/complaints" element={<Complaints />} />
            <Route path="/admin/settings" element={<OwnerSettings />} />
            <Route path="/success" element={<Success />} />
            <Route path="/notifications" element={<Notifications />} />
            <Route path="/category/:id" element={<CategoryDetail />} />
            <Route path="/stores" element={<Stores />} />
          </Routes>
        </Suspense>
      </RouteErrorBoundary>
      
      {/* Main button fallback, cart bar and navigation, stacked; content is padded by their height (pb-dock) */}
      <AppDock />

      {/* In-app stand-ins for Telegram's popups (showAlert / showConfirm outside Telegram) */}
      <DialogHost />
    </div>
  );
}

function OfflineScreen() {
  const { t } = useLanguageStore();
  return (
    <div className="fixed inset-0 z-50 flex flex-col items-center justify-center gap-3 bg-page p-6 text-center animate-fade-in" role="alert">
      <span className="flex size-14 items-center justify-center rounded-full bg-danger-soft text-danger-soft-fg">
        <WifiOff aria-hidden="true" className="size-7" />
      </span>
      <h2 className="text-title font-bold text-fg">{t('offlineTitle')}</h2>
      <p className="max-w-xs text-body text-fg-muted">{t('offlineText')}</p>
      <Button icon={RotateCw} className="mt-3 w-full max-w-xs" onClick={() => window.location.reload()}>
        {t('reload')}
      </Button>
    </div>
  );
}

function App() {
  const [isOffline, setIsOffline] = useState(!navigator.onLine);

  useEffect(() => {
    const handleOnline = () => setIsOffline(false);
    const handleOffline = () => setIsOffline(true);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  if (isOffline) {
    return <OfflineScreen />;
  }

  // Securely Block Direct Browser Access (Direct Web Open) in Production/Non-Dev Mode
  const isRunningOutsideTelegram = !window.Telegram || !window.Telegram.WebApp || !window.Telegram.WebApp.initData;
  const isDevMode = isDebugMode || new URLSearchParams(window.location.search).get('dev') === 'true';

  if (isRunningOutsideTelegram && !isDevMode) {
    return <OnlyTelegramBlocker />;
  }

  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AppContent />
      </BrowserRouter>
    </QueryClientProvider>
  );
}

export default App;
// Force Vite HMR
