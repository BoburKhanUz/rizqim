import { Suspense, useEffect, useState } from 'react';
import { BrowserRouter, Routes, Route, NavLink, useLocation, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { initTelegramApp, hapticFeedback } from './lib/telegram';
import { useTelegramAuth } from './hooks/useTelegramAuth';
import { useRealtimeOrders } from './hooks/useRealtimeOrders';
import { useCartStore } from './store/cartStore';
import { useLanguageStore } from './store/languageStore';
import { useAuthStore } from './store/authStore';
import { ShoppingBag, User as UserIcon, BarChart3, Activity, Building2, PlusCircle, LayoutGrid, Users, Home as HomeIcon, List, ClipboardList } from 'lucide-react';
import api from './services/api';
import PhoneVerificationModal from './components/PhoneVerificationModal';
import { FullScreenLoader, ErrorFallback, RouteErrorBoundary, RouteFallback } from './components/Feedback';
import { lazyWithRetry } from './lib/lazyWithRetry';
import { formatUzbekSum } from './lib/format';
import { getRoleHomePath, isCustomerRole } from './lib/roles';
import type { User } from './types';

// The customer's landing page is part of the entry bundle; every other page is its own chunk that is
// downloaded when its route is first visited (see lazyWithRetry), so a courier never downloads the
// owner screens and a customer never downloads the superadmin ones.
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
const AdminAnalytics = lazyWithRetry(() => import('./pages/AdminAnalytics'));
const AdminReports = lazyWithRetry(() => import('./pages/AdminReports'));
const OwnerMenuManagement = lazyWithRetry(() => import('./pages/OwnerMenuManagement'));
const OwnerProductForm = lazyWithRetry(() => import('./pages/OwnerProductForm'));
const OwnerEmployees = lazyWithRetry(() => import('./pages/OwnerEmployees'));
const OwnerPayments = lazyWithRetry(() => import('./pages/OwnerPayments'));
const OwnerSettings = lazyWithRetry(() => import('./pages/OwnerSettings'));
const SuperadminDashboard = lazyWithRetry(() => import('./pages/SuperadminDashboard'));
const SuperadminStores = lazyWithRetry(() => import('./pages/SuperadminStores'));
const SuperadminCreateStore = lazyWithRetry(() => import('./pages/SuperadminCreateStore'));

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
    <div className="p-4 m-4 bg-secondary rounded-xl border border-hint/20 text-xs text-left">
      <h2 className="font-bold mb-2">🛠 Debug Mode (VITE_APP_DEBUG=true)</h2>
      {error && <p className="text-red-500 mb-2">Error: {error}</p>}
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

// Premium Navigation Component
function BottomNavBar() {
  const location = useLocation();
  const { getTotalItems } = useCartStore();
  const { t, language } = useLanguageStore();
  const { user } = useAuthStore();

  // Hide BottomNavBar on the success page
  const hidePaths = ['/success'];
  if (hidePaths.includes(location.pathname)) return null;

  const totalItems = getTotalItems();

  if (user?.role === 'super_admin') {
    return (
      <nav className="fixed bottom-0 left-0 right-0 z-40 max-w-md mx-auto flex items-center justify-around py-3.5 border-t border-hint/10 bg-bg/85 backdrop-blur-md shadow-lg safe-area-bottom">
        <NavLink 
          to="/superadmin/dashboard" 
          onClick={() => hapticFeedback('light')}
          className={({ isActive }) => `flex flex-col items-center flex-1 py-1 transition-transform active:scale-95 ${isActive ? 'text-primary font-extrabold' : 'text-hint'}`}
        >
          <Activity className="w-5 h-5" />
          <span className="text-[10px] font-bold mt-1">Asosiy</span>
        </NavLink>

        <NavLink 
          to="/superadmin/stores" 
          onClick={() => hapticFeedback('light')}
          className={({ isActive }) => `flex flex-col items-center flex-1 py-1 transition-transform active:scale-95 ${isActive ? 'text-primary font-extrabold' : 'text-hint'}`}
        >
          <Building2 className="w-5 h-5" />
          <span className="text-[10px] font-bold mt-1">Restoranlar</span>
        </NavLink>

        <NavLink 
          to="/superadmin/stores/create" 
          onClick={() => hapticFeedback('light')}
          className={({ isActive }) => `flex flex-col items-center flex-1 py-1 transition-transform active:scale-95 ${isActive ? 'text-primary font-extrabold' : 'text-hint'}`}
        >
          <PlusCircle className="w-5 h-5" />
          <span className="text-[10px] font-bold mt-1">Qo'shish</span>
        </NavLink>

        <NavLink 
          to="/profile" 
          onClick={() => hapticFeedback('light')}
          className={({ isActive }) => `flex flex-col items-center flex-1 py-1 transition-transform active:scale-95 ${isActive ? 'text-primary font-extrabold' : 'text-hint'}`}
        >
          <UserIcon className="w-5 h-5" />
          <span className="text-[10px] font-bold mt-1">Profil</span>
        </NavLink>
      </nav>
    );
  }

  if (user?.role === 'owner') {
    return (
      <nav className="fixed bottom-0 left-0 right-0 z-40 max-w-md mx-auto flex items-center justify-around py-3.5 border-t border-hint/10 bg-bg/85 backdrop-blur-md shadow-lg safe-area-bottom">
        <NavLink 
          to="/admin/analytics" 
          onClick={() => hapticFeedback('light')}
          className={({ isActive }) => `flex flex-col items-center flex-1 py-1 transition-transform active:scale-95 ${isActive ? 'text-primary font-extrabold' : 'text-hint'}`}
        >
          <BarChart3 className="w-5 h-5" />
          <span className="text-[10px] font-bold mt-1">{language === 'uz' ? 'Analitika' : 'Аналитика'}</span>
        </NavLink>

        <NavLink 
          to="/admin/catalog" 
          onClick={() => hapticFeedback('light')}
          className={({ isActive }) => `flex flex-col items-center flex-1 py-1 transition-transform active:scale-95 ${isActive ? 'text-primary font-extrabold' : 'text-hint'}`}
        >
          <LayoutGrid className="w-5 h-5" />
          <span className="text-[10px] font-bold mt-1">{language === 'uz' ? 'Katalog' : 'Меню'}</span>
        </NavLink>

        <NavLink 
          to="/manager" 
          onClick={() => hapticFeedback('light')}
          className={({ isActive }) => `flex flex-col items-center flex-1 py-1 transition-transform active:scale-95 ${isActive ? 'text-primary font-extrabold' : 'text-hint'}`}
        >
          <ClipboardList className="w-5 h-5" />
          <span className="text-[10px] font-bold mt-1">{language === 'uz' ? 'Buyurtmalar' : 'Заказы'}</span>
        </NavLink>

        <NavLink 
          to="/admin/employees" 
          onClick={() => hapticFeedback('light')}
          className={({ isActive }) => `flex flex-col items-center flex-1 py-1 transition-transform active:scale-95 ${isActive ? 'text-primary font-extrabold' : 'text-hint'}`}
        >
          <Users className="w-5 h-5" />
          <span className="text-[10px] font-bold mt-1">Xodimlar</span>
        </NavLink>

        <NavLink 
          to="/profile" 
          onClick={() => hapticFeedback('light')}
          className={({ isActive }) => `flex flex-col items-center flex-1 py-1 transition-transform active:scale-95 ${isActive ? 'text-primary font-extrabold' : 'text-hint'}`}
        >
          <UserIcon className="w-5 h-5" />
          <span className="text-[10px] font-bold mt-1">{language === 'uz' ? 'Profil' : 'Профиль'}</span>
        </NavLink>
      </nav>
    );
  }

  if (user?.role === 'manager') {
    return (
      <nav className="fixed bottom-0 left-0 right-0 z-40 max-w-md mx-auto flex items-center justify-around py-3.5 border-t border-hint/10 bg-bg/85 backdrop-blur-md shadow-lg safe-area-bottom">
        <NavLink 
          to="/manager" 
          onClick={() => hapticFeedback('light')}
          className={({ isActive }) => `flex flex-col items-center flex-1 py-1 transition-transform active:scale-95 ${isActive ? 'text-primary font-extrabold' : 'text-hint'}`}
        >
          <ClipboardList className="w-5 h-5" />
          <span className="text-[10px] font-bold mt-1">{language === 'uz' ? 'Buyurtmalar' : 'Заказы'}</span>
        </NavLink>

        <NavLink 
          to="/profile" 
          onClick={() => hapticFeedback('light')}
          className={({ isActive }) => `flex flex-col items-center flex-1 py-1 transition-transform active:scale-95 ${isActive ? 'text-primary font-extrabold' : 'text-hint'}`}
        >
          <UserIcon className="w-5 h-5" />
          <span className="text-[10px] font-bold mt-1">{language === 'uz' ? 'Profil' : 'Профиль'}</span>
        </NavLink>
      </nav>
    );
  }

  if (user?.role === 'courier') {
    return (
      <nav className="fixed bottom-0 left-0 right-0 z-40 max-w-md mx-auto flex items-center justify-around py-3.5 border-t border-hint/10 bg-bg/85 backdrop-blur-md shadow-lg safe-area-bottom">
        <NavLink 
          to="/courier" 
          onClick={() => hapticFeedback('light')}
          className={({ isActive }) => `flex flex-col items-center flex-1 py-1 transition-transform active:scale-95 ${isActive ? 'text-primary font-extrabold' : 'text-hint'}`}
        >
          <ClipboardList className="w-5 h-5" />
          <span className="text-[10px] font-bold mt-1">{language === 'uz' ? 'Yetkazmalar' : 'Доставки'}</span>
        </NavLink>

        <NavLink 
          to="/profile" 
          onClick={() => hapticFeedback('light')}
          className={({ isActive }) => `flex flex-col items-center flex-1 py-1 transition-transform active:scale-95 ${isActive ? 'text-primary font-extrabold' : 'text-hint'}`}
        >
          <UserIcon className="w-5 h-5" />
          <span className="text-[10px] font-bold mt-1">{language === 'uz' ? 'Profil' : 'Профиль'}</span>
        </NavLink>
      </nav>
    );
  }

  return (
    <nav className="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur-md border-t border-slate-100 pt-3.5 flex items-center justify-around z-40 max-w-md mx-auto shadow-[0_-6px_25px_rgba(0,0,0,0.03)] rounded-t-3xl safe-area-bottom">
      <NavLink 
        to="/" 
        onClick={() => hapticFeedback('light')}
        className={({ isActive }) => `flex flex-col items-center gap-1.5 relative flex-1 transition-all duration-200 active:scale-90 ${isActive ? 'text-amber-500 scale-105 font-black' : 'text-slate-400 hover:text-slate-600'}`}
      >
        <span className="leading-none"><HomeIcon className="w-5 h-5" /></span>
        <span className="text-[10px] font-black">{t('catalog')}</span>
      </NavLink>

      <NavLink 
        to="/cart" 
        onClick={() => hapticFeedback('light')}
        className={({ isActive }) => `flex flex-col items-center gap-1.5 relative flex-1 transition-all duration-200 active:scale-90 ${isActive ? 'text-amber-500 scale-105 font-black' : 'text-slate-400 hover:text-slate-600'}`}
      >
        <div className="relative">
          <span className="leading-none"><ShoppingBag className="w-5 h-5" /></span>
          {totalItems > 0 && (
            <span className="absolute -top-1.5 -right-2 bg-red-500 text-white font-black text-[8px] w-[18px] h-[18px] rounded-full flex items-center justify-center border-2 border-white animate-pulse">
              {totalItems}
            </span>
          )}
        </div>
        <span className="text-[10px] font-black">{t('cart')}</span>
      </NavLink>

      <NavLink 
        to="/orders" 
        onClick={() => hapticFeedback('light')}
        className={({ isActive }) => `flex flex-col items-center gap-1.5 relative flex-1 transition-all duration-200 active:scale-90 ${isActive ? 'text-amber-500 scale-105 font-black' : 'text-slate-400 hover:text-slate-600'}`}
      >
        <span className="leading-none"><List className="w-5 h-5" /></span>
        <span className="text-[10px] font-black">{t('myOrders')}</span>
      </NavLink>

      <NavLink 
        to="/profile" 
        onClick={() => hapticFeedback('light')}
        className={({ isActive }) => `flex flex-col items-center gap-1.5 relative flex-1 transition-all duration-200 active:scale-90 ${isActive ? 'text-amber-500 scale-105 font-black' : 'text-slate-400 hover:text-slate-600'}`}
      >
        <span className="leading-none"><UserIcon className="w-5 h-5" /></span>
        <span className="text-[10px] font-black">{language === 'uz' ? 'Profil' : 'Профиль'}</span>
      </NavLink>
    </nav>
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

  return (
    <div className="fixed inset-0 flex flex-col items-center justify-center bg-[#0e1621] p-6 text-center z-50 text-white select-none">
      {/* Background Glow */}
      <div className="absolute top-1/4 left-1/2 -translate-x-1/2 w-72 h-72 rounded-full bg-primary/20 blur-[100px] pointer-events-none"></div>

      <div className="bg-[#17212b]/80 border border-primary/20 backdrop-blur-xl rounded-[36px] p-8 shadow-2xl max-w-sm w-full flex flex-col gap-6 items-center relative overflow-hidden">
        {/* Neon Border Glow */}
        <div className="absolute inset-0 border border-primary/10 rounded-[36px] pointer-events-none"></div>

        {/* Telegram 3D-Like Icon */}
        <div className="w-20 h-20 bg-gradient-to-tr from-primary to-blue-400 rounded-full flex items-center justify-center shadow-lg shadow-primary/30 relative">
          <svg className="w-10 h-10 text-white translate-x-[-1px] translate-y-[1px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
        </div>

        {/* Text Details */}
        <div className="flex flex-col gap-2.5">
          <h2 className="text-xl font-black tracking-tight text-white">Telegram Mini App</h2>
          <p className="text-xs text-gray-400 font-medium leading-relaxed px-2">
            Ushbu tizim faqat **Telegram** ichida ishlashga mo'ljallangan! Brauzer orqali to'g'ridan-to'g'ri kirish taqiqlangan.
          </p>
        </div>

        {/* QR Code Graphic (SVG) */}
        <div className="bg-white p-4 rounded-[28px] shadow-inner relative group border border-gray-700/10">
          <svg className="w-36 h-36 text-[#0e1621]" viewBox="0 0 100 100" fill="currentColor">
            {/* Elegant Custom Vector QR Mock with Telegram Logo inside */}
            <path d="M5,5 h25 v25 h-25 z M10,10 h15 v15 h-15 z M17,17 h1 v1 h-1 z" />
            <path d="M70,5 h25 v25 h-25 z M75,10 h15 v15 h-15 z M82,17 h1 v1 h-1 z" />
            <path d="M5,70 h25 v25 h-25 z M10,75 h15 v15 h-15 z M17,82 h1 v1 h-1 z" />
            <path d="M40,5 h10 v5 h-5 v10 h-5 z M45,20 h5 v5 h-5 z M55,5 h10 v5 h-10 z M60,15 h5 v10 h-5 z" />
            <path d="M5,40 h10 v5 h-5 v10 h-5 z M20,45 h5 v5 h-5 z M35,40 h15 v5 h-10 v10 h-5 z" />
            <path d="M70,40 h15 v5 h-10 v5 h5 v5 h-10 z M90,45 h5 v5 h-5 z" />
            <path d="M40,60 h10 v10 h-5 v10 h-5 z M50,80 h15 v5 h-10 v10 h-5 z" />
            <path d="M70,70 h15 v5 h-10 v10 h5 v10 h-10 z M90,80 h5 v5 h-5 z" />
            <circle cx="50" cy="50" r="12" fill="#0088cc" />
            <path d="M46,50 l6,3.5 l-1.5,1.5 l-4.5,-5 z" fill="white" />
            <polygon points="45,50 55,46 51,52" fill="white" />
          </svg>
        </div>

        <p className="text-[10px] text-gray-400 font-bold uppercase tracking-wider">
          Telefoningiz orqali QR kodni skanerlang
        </p>

        {/* CTA Button */}
        <a 
          href={botLink}
          target="_blank"
          rel="noopener noreferrer"
          className="w-full bg-primary hover:bg-primary/90 text-white font-black text-xs py-4 rounded-2xl active:scale-95 transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2"
        >
          <span>💬 Bot orqali ochish</span>
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

function FloatingCartBar() {
  const location = useLocation();
  // Select the computed values, not the getter functions: a getter's reference never changes, so the bar would not
  // re-render when items are added
  const totalItems = useCartStore((state) => state.getTotalItems());
  const totalAmount = useCartStore((state) => state.getSubtotal());

  const hidePaths = ['/cart', '/success', '/orders', '/profile', '/complaints', '/notifications'];
  const shouldHide = hidePaths.some(p => location.pathname.startsWith(p)) || 
                     location.pathname.includes('/admin') || 
                     location.pathname.includes('/superadmin') || 
                     location.pathname === '/courier' || 
                     location.pathname === '/manager';

  if (totalItems === 0 || shouldHide) return null;

  return (
    <div className="fixed bottom-[74px] left-0 right-0 max-w-md mx-auto px-4 z-30 transform transition animate-scale-up">
      <NavLink
        to="/cart"
        onClick={() => hapticFeedback('medium')}
        className="w-full bg-amber-500 hover:bg-amber-600 text-white font-black py-3.5 px-5 rounded-2xl flex items-center justify-between shadow-lg shadow-amber-200 transition-all active:scale-[0.98] cursor-pointer"
      >
        <div className="flex items-center space-x-2.5">
          <div className="bg-white/20 px-2.5 py-0.5 rounded-lg text-xs font-black relative flex items-center">
            🛒 {totalItems}
          </div>
          <span className="text-xs tracking-wide font-extrabold">Savatchani ko'rish</span>
        </div>
        <div className="flex items-center space-x-1.5 text-xs">
          <span className="font-black text-sm">
            {formatUzbekSum(totalAmount)}
          </span>
          <span>➔</span>
        </div>
      </NavLink>
    </div>
  );
}

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

  usePrefetchCustomerPages(isCustomer && isAuthenticated);
  useRealtimeOrders(); // order events over the socket, once signed in (a no-op without VITE_REVERB_APP_KEY)

  useEffect(() => {
    initTelegramApp();
  }, []);

  useEffect(() => {
    if (isCustomer) {
      document.body.style.backgroundColor = '#f8fafc'; // bg-slate-50
      document.body.style.color = '#0f172a'; // text-slate-900
      document.documentElement.style.backgroundColor = '#f8fafc';
    } else {
      document.body.style.backgroundColor = ''; // Restore standard
      document.body.style.color = '';
      document.documentElement.style.backgroundColor = '';
    }
  }, [isCustomer]);

  if (isAuthenticating) return <FullScreenLoader message="Connecting securely..." />;
  
  if (error && !isAuthenticated) {
    return (
      <>
        <ErrorFallback error={error} />
        <AuthDebugger error={error} token={token} user={user} isAuthenticated={isAuthenticated} />
      </>
    );
  }

  return (
    <div className={`min-h-screen pb-24 relative ${isCustomer ? 'bg-slate-50' : 'bg-bg'}`}>
      <AuthDebugger error={error} token={token} user={user} isAuthenticated={isAuthenticated} />
      
      {/* Automatically Prompts User for Phone Number on first login if not yet provided */}
      <PhonePromptModal />

      <RouteErrorBoundary resetKey={location.pathname}>
        <Suspense fallback={<RouteFallback />}>
          <Routes>
            <Route path="/" element={<HomeRoute />} />
            <Route path="/superadmin/dashboard" element={<SuperadminDashboard />} />
            <Route path="/superadmin/stores" element={<SuperadminStores />} />
            <Route path="/superadmin/stores/create" element={<SuperadminCreateStore />} />
            <Route path="/store/:id" element={<StoreDetail />} />
            <Route path="/courier" element={<CourierDashboard />} />
            <Route path="/manager" element={<ManagerDashboard />} />
            <Route path="/admin/analytics" element={<AdminAnalytics />} />
            <Route path="/admin/reports" element={<AdminReports />} />
            <Route path="/admin/catalog" element={<OwnerMenuManagement />} />
            <Route path="/admin/catalog/create" element={<OwnerProductForm />} />
            <Route path="/admin/catalog/edit/:id" element={<OwnerProductForm />} />
            <Route path="/admin/employees" element={<OwnerEmployees />} />
            <Route path="/admin/payments" element={<OwnerPayments />} />
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
      
      {/* Floating Sticky Cart Indicator Bar */}
      <FloatingCartBar />

      {/* Native-style Sticky Bottom Navigation Bar */}
      <BottomNavBar />
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
    return (
      <div className="fixed inset-0 flex flex-col items-center justify-center bg-bg p-6 text-center z-50 animate-fade-in">
        <div className="text-4xl mb-4">📶</div>
        <h2 className="text-xl font-black text-text-h mb-2">
          Internet aloqasi mavjud emas
        </h2>
        <p className="text-xs text-hint mb-6 max-w-xs leading-relaxed font-semibold">
          Iltimos, internet aloqasini tekshiring va qaytadan urining.
        </p>
        <button 
          onClick={() => window.location.reload()} 
          className="bg-primary hover:bg-primary/95 text-white px-6 py-3.5 rounded-2xl font-black text-xs w-full max-w-xs active:scale-95 transition-transform shadow-md shadow-primary/20 cursor-pointer"
        >
          Qayta yuklash
        </button>
      </div>
    );
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
