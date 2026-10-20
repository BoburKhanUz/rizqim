import type { Pagination } from './api';
import type { Order } from './order';
import type { ApiUser } from './user';

export interface NotificationItem {
  id: number;
  title: string;
  message: string;
  type: string; // NotificationLog.type, e.g. "telegram_customer_accepted" or "complaint_reply"
  status: string;
  is_read: boolean;
  read_at: string | null;
  sent_at: string;
}

// GET /notifications
export interface NotificationsPage {
  items: NotificationItem[];
  unread_count: number;
  pagination: Pagination;
}

export type ComplaintType =
  | 'WRONG_ITEM'
  | 'ORDER_NOT_RECEIVED'
  | 'QUALITY_ISSUE'
  | 'COURIER_ISSUE'
  | 'PAYMENT_ISSUE'
  | 'OTHER';

// The complaints.status enum.
export type ComplaintStatus = 'pending' | 'owner_replied' | 'in_review' | 'closed';

export interface Complaint {
  id: number;
  uuid: string;
  order_id: number;
  user_id: number;
  type: ComplaintType;
  description: string;
  status: ComplaintStatus;
  reply: string | null;
  resolution_comment: string | null;
  resolved_by: number | null;
  created_at: string;
  updated_at: string;
  order?: Order | null;
  user?: ApiUser | null; // only for owners/managers
}

// POST /complaints
export interface NewComplaint {
  order_id: number;
  type: ComplaintType;
  description: string;
}
