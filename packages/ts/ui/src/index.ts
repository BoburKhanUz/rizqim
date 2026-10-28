// @rizqim/ui: the Rizqim design system shared by the Mini App and the partner web (docs/DESIGN.md).
// Styles: import '@rizqim/ui/styles.css' after Tailwind (see README.md). Nothing here knows about Telegram, a router or
// a language store: texts and the language come in as props.
export { Button, type ButtonProps } from './components/Button';
export { buttonClasses, type ButtonSize, type ButtonVariant } from './components/buttonStyles';
export { IconButton, type IconButtonProps } from './components/IconButton';
export { StatusBadge } from './components/StatusBadge';
export { InlineError } from './components/InlineError';
export { EmptyState } from './components/EmptyState';
export { Card, Section } from './components/Card';
export { Dialog } from './components/Dialog';
export { ConfirmDialog } from './components/ConfirmDialog';
export { PageHeader } from './components/PageHeader';
export { FormError, Notice } from './components/Notice';
export { Pill, type PillTone } from './components/Pill';
export { Switch } from './components/Switch';
export { Tabs, TAB_CLASS, tabTone } from './components/Tabs';
export { Spinner } from './components/Spinner';
export { cx } from './components/cx';
export { Field } from './components/Field';
export { fieldClass } from './components/fieldStyles';
export { CardSkeletons, Skeleton } from './components/Skeleton';
export { FOCUSABLE, keepTabInside } from './lib/focus';

export { formatDate, formatDateTime, formatMoney, formatNumber, formatOrderCode, formatTime, NBSP } from './lib/format';
export { parseAmount, sumAmounts, toAmount } from './lib/money';
export { getOrderStatusLook, isOrderStatus, TONE_BADGE, type OrderStatusStyle, type StatusTone } from './lib/orderStatus';
export type { Amount, Language, OrderStatus } from './lib/types';
