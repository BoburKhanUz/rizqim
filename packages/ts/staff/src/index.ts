// @rizqim/staff: what the Mini App's staff screens and the partner web panel share (docs/PHASE2_PLAN.md, "Asosiy tamoyil").
// Business rules stay in the backend: this package only decides how things are shown (statuses, words, formats) and how
// the API is called. Read README.md.

export type * from './types';

// Order statuses as data (labels, colour groups, next actions)
export {
  CANCELABLE_STATUSES,
  FINISHED_STATUSES,
  GROUP_STATUSES,
  isCancellation,
  isFinishedStatus,
  isOpenStatus,
  isOrderStatus,
  getOrderActions,
  ORDER_STATUSES,
  OPEN_STATUSES,
  STATUS_GROUPS,
  STATUS_TONE,
  statusGroup,
  statusTone,
  WITH_COURIER_STATUSES,
  type OrderAction,
  type OrderActionId,
  type OrderActionRole,
  type StatusGroup,
} from './orders/status';
export { CANCEL_NOTE_MAX_LENGTH, getStaffCancelReasons, STAFF_CANCEL_REASONS } from './orders/cancel';
export { formatDuration, getStageDurations, stageRows, type StageDurations } from './orders/stages';
export { formatModifiers, getItemModifiers, getShippingAddress, type OrderItemModifier, type ShippingAddress } from './orders/display';
// P2-33: the new-order chime and the waiting-time thresholds of the staff screens (partner web and Mini App alike)
export { createChime, type Chime } from './orders/chime';
export {
  getOrderWaiting,
  WAITING_RUNNING_STATUSES,
  WAITING_THRESHOLDS,
  waitingSecondsOf,
  waitingToneOf,
  type WaitTone,
} from './orders/waiting';
export {
  applyFinishedOrderChange,
  mergeOrders,
  refetchOrders,
  someOrderLeft,
  type FinishedOrderChange,
  type OrderRef,
} from './orders/cache';

// Words
export {
  ACTION_LABELS,
  actionLabel,
  CANCEL_REASON_LABELS,
  cancelReasonLabel,
  COURIER_ACTION_LABELS,
  normalizePaymentMethod,
  ORDER_TYPE_LABELS,
  orderTypeLabel,
  PAYMENT_METHOD_LABELS,
  PAYMENT_METHODS,
  paymentMethodLabel,
  STAFF_MESSAGES,
  STAFF_STATUS_LABELS,
  staffText,
  statusLabel,
  type PaymentMethodId,
  type StaffMessageKey,
} from './i18n/messages';
export { fillParams, isLanguage, LANGUAGES, localize, type Language, type Localized } from './i18n/localize';

// Formats
export * from './format';

// API layer
export { createStaffApi, toApiRoot, type StaffApi, type StaffApiOptions } from './api/client';
export { createAuthFetch, type AuthAdapter, type RequestFetch } from './api/auth';
export {
  getApiErrorMessage,
  getApiErrorStatus,
  getApiFieldError,
  isStaffApiError,
  StaffApiError,
  unwrap,
} from './api/errors';
export type { JsonBody, JsonRequest, OkData } from './api/types';
export * from './api/orders';
export * from './api/menu';
export * from './api/team';
export * from './api/settings';
export * from './api/payments';
export * from './api/analytics';
export * from './api/superadmin';

// TanStack Query hooks
export { setDefaultStaffApi, StaffApiProvider, useStaffApi } from './hooks/context';
export { staffKeys } from './hooks/keys';
export * from './hooks/orders';
export * from './hooks/menu';
export * from './hooks/team';
export * from './hooks/business';

// P2-24: sales reports, CSV download and the money history of the partner web panel
export * from './api/reports';
export * from './hooks/reports';

// P2-22: modifier groups, channel prices, branch overrides and the branch list of the partner web menu screens
export * from './api/menuAdmin';
export * from './hooks/menuAdmin';

// P2-23: staff, branches and working hours, delivery zones, store settings, service fees and terminals of the partner web management screens
export * from './api/management';
export * from './hooks/management';

// P2-21: the live orders board (GET /staff/orders*, POST /staff/orders/{uuid}/status, branch list for the picker)
export * from './api/board';
export * from './hooks/board';

// P3-23: fiscal setup and receipts, halls and the floor layout, room tariffs, POS setup, bookings, customer accounts (nasiya)
// and shifts with X/Z of the partner web POS screens (the Mini App's shift summary and debtors share the last two)
export * from './api/idempotency';
export * from './api/fiscal';
export * from './hooks/fiscal';
export * from './api/floor';
export * from './hooks/floor';
export * from './api/posSetup';
export * from './hooks/posSetup';
export * from './api/bookings';
export * from './hooks/bookings';
export * from './api/accounts';
export * from './hooks/accounts';
export * from './api/shifts';
export * from './hooks/shifts';
