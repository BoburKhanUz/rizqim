// The chunks of the customer pages that are prefetched before they are opened (App.tsx after sign-in, OrderPlaced after
// checkout). The module is cached once loaded: calling a loader again returns the same module.
export const loadStoreDetail = () => import('../pages/StoreDetail');
export const loadCategoryDetail = () => import('../pages/CategoryDetail');
export const loadStores = () => import('../pages/Stores');
export const loadOrders = () => import('../pages/Orders');
