// Component files that do not follow the design system yet (raw slate/amber/white classes, the legacy staff aliases
// bg-bg/text-hint/bg-primary, text under 12px, native alert/confirm). designSystem.test.ts skips them and checks every
// other .tsx file.
//
// MIGRATING A FILE: switch it to the tokens and components of docs/DESIGN.md, delete its line here, run `npm test`.
// The test fails if a file listed here is already clean, so remove it in the same change. Never add a file.

export const NOT_YET_MIGRATED: readonly string[] = [
  // Wave B / customer catalog & cart
  'src/pages/Home.tsx',
  'src/pages/StoreDetail.tsx',
  'src/pages/CategoryDetail.tsx',
  'src/pages/Stores.tsx',
  'src/pages/Cart.tsx',
  'src/pages/Success.tsx',
  'src/components/VariantSelectorModal.tsx',
  'src/components/HeroPromoSlider.tsx',
  'src/components/PhoneVerificationModal.tsx',
  'src/features/address/AddressLocationField.tsx',
  'src/components/map/LazyMapPicker.tsx',
  'src/components/map/MapErrorBoundary.tsx',
  'src/components/map/MapPicker.tsx',

  // Wave B / customer orders & profile (Complaints, Notifications and the cancel dialogs serve staff too)
  'src/pages/Orders.tsx',
  'src/pages/CustomerProfile.tsx',
  'src/pages/Notifications.tsx',
  'src/pages/Complaints.tsx',
  'src/components/NotificationCenter.tsx',
  'src/components/CancelDialogs.tsx',
  'src/components/OrderListParts.tsx',

  // Wave B / staff screens
  'src/pages/ManagerDashboard.tsx',
  'src/pages/CourierDashboard.tsx',
  'src/pages/AdminAnalytics.tsx',
  'src/pages/AdminReports.tsx',
  'src/pages/OwnerEmployees.tsx',
  'src/pages/OwnerMenuManagement.tsx',
  'src/pages/OwnerPayments.tsx',
  'src/pages/OwnerProductForm.tsx',
  'src/pages/OwnerSettings.tsx',
  'src/pages/Profile.tsx',
  'src/pages/SuperadminCreateStore.tsx',
  'src/pages/SuperadminDashboard.tsx',
  'src/pages/SuperadminStores.tsx',
];
