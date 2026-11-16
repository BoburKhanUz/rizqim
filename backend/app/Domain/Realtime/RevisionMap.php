<?php

namespace App\Domain\Realtime;

/**
 * Wave M2: which change token a write to each table moves. EVERY table of the schema is listed here
 * (RevisionMapCoverageTest fails on a table that is missing), so a new table cannot be added without deciding.
 *
 * A write is any insert, update, upsert or delete through the query builder (Eloquent saves, mass updates,
 * DB::table(), pivots): RevisionQueryBuilder resolves the rows' scopes and ChangeRevision touches them after commit.
 *
 * Rules, one or more per table:
 * - [self::BRANCH]: the row's branch_id; a row without a branch (store-wide) touches every branch of its store_id.
 * - [self::STORE]: the row's store_id: touchStore() (marketplace-visible or menu data; it reaches all its branches).
 * - [self::STORE_BRANCHES]: every branch of the row's store_id, not the store token (store-wide POS configuration that
 *   the marketplace does not show, so the catalogue document is not rebuilt).
 * - [self::USER, column]: touchUser() for that column (a customer's own feeds, a courier's list).
 * - [self::VIA, column, parent table]: the rule of the parent row the column points at (order_items -> orders).
 * - [self::ALL_STORES]: platform-wide reference data every store shows (rare writes, touches every store).
 * - [self::FEATURE_SCOPE, column]: a Pennant row; `App\Models\Store|{id}` touches that store, any other scope every store.
 * - [self::NONE, reason]: no screen that polls or listens shows this table. State why.
 *
 * When in doubt, touch: a missing touch is a stale till or kitchen board, an extra one costs one full read.
 */
final class RevisionMap
{
    public const BRANCH = 'branch';

    public const STORE = 'store';

    public const STORE_BRANCHES = 'store_branches';

    public const USER = 'user';

    public const VIA = 'via';

    public const ALL_STORES = 'all_stores';

    public const FEATURE_SCOPE = 'feature_scope';

    public const NONE = 'none';

    /**
     * Columns whose change alone touches nothing: liveness stamps written on every request or every minute. A write
     * that changes anything else touches as usual.
     *
     * @var array<string, list<string>>
     */
    public const QUIET_COLUMNS = [
        'devices' => ['last_seen_at', 'sync_reported_at', 'sync_clock_skew_seconds', 'updated_at'],
        'users' => ['platform_last_login_at', 'remember_token', 'two_factor_last_timestep', 'updated_at'],
    ];

    /** @var array<string, list<list<string>>> */
    public const TABLES = [
        // Orders and everything hung on them: the till, KDS, waiter, display, LiveOrders, the customer's orders
        'orders' => [[self::BRANCH], [self::USER, 'user_id'], [self::USER, 'courier_id']],
        'order_items' => [[self::VIA, 'order_id', 'orders']],
        'order_assignments' => [[self::VIA, 'order_id', 'orders'], [self::USER, 'courier_id']],
        'order_bills' => [[self::VIA, 'order_id', 'orders']],
        'order_bill_items' => [[self::VIA, 'bill_id', 'order_bills']],
        'order_charge_waivers' => [[self::VIA, 'order_id', 'orders']],
        'order_ledger_reversals' => [[self::VIA, 'order_id', 'orders']],
        'order_packaging_removals' => [[self::VIA, 'order_id', 'orders']],
        'order_refund_requests' => [[self::VIA, 'order_id', 'orders']],
        'order_status_histories' => [[self::VIA, 'order_id', 'orders']],
        'order_status_logs' => [[self::VIA, 'order_id', 'orders']],
        'order_sla_events' => [[self::VIA, 'order_id', 'orders']],
        'payments' => [[self::VIA, 'order_id', 'orders'], [self::VIA, 'bill_id', 'order_bills']],
        'fiscal_receipts' => [[self::BRANCH]],
        'pos_bill_print_requests' => [[self::BRANCH]],
        'pos_qr_payments' => [[self::BRANCH]],
        'pos_qr_refund_attempts' => [[self::VIA, 'order_id', 'orders']],
        'pos_refunds' => [[self::BRANCH]],
        'pos_tips' => [[self::BRANCH]],
        'pos_approvals' => [[self::BRANCH]],
        'pos_sync_operations' => [[self::BRANCH]],
        'pos_queue_blocks' => [[self::BRANCH]],
        'telegram_actions' => [[self::NONE, 'outbox of Telegram button presses; the order row it changes is touched itself']],
        'notification_logs' => [[self::USER, 'user_id']],
        'notification_preferences' => [[self::USER, 'user_id']],
        'complaints' => [[self::USER, 'user_id'], [self::VIA, 'order_id', 'orders']],
        'complaint_replies' => [[self::VIA, 'complaint_id', 'complaints']],
        'store_ratings' => [[self::STORE], [self::USER, 'user_id']],
        'addresses' => [[self::USER, 'user_id']],

        // Floor, shifts, bookings, waitlist, banquets
        'halls' => [[self::BRANCH]],
        'dining_tables' => [[self::BRANCH]],
        'pos_shifts' => [[self::BRANCH]],
        'cash_movements' => [[self::BRANCH]],
        'bank_terminal_reports' => [[self::BRANCH]],
        'staff_work_sessions' => [[self::BRANCH]],
        'bookings' => [[self::BRANCH]],
        'booking_items' => [[self::VIA, 'booking_id', 'bookings']],
        'booking_tables' => [[self::VIA, 'booking_id', 'bookings']],
        'booking_payments' => [[self::BRANCH]],
        'pos_waitlist_entries' => [[self::BRANCH]],
        'room_tariffs' => [[self::BRANCH]],
        'room_tariff_calendars' => [[self::BRANCH]],
        'banquet_events' => [[self::BRANCH]],
        'banquet_event_halls' => [[self::VIA, 'banquet_event_id', 'banquet_events']],
        'banquet_event_lines' => [[self::VIA, 'banquet_event_id', 'banquet_events']],
        'banquet_event_staff' => [[self::VIA, 'banquet_event_id', 'banquet_events']],
        'banquet_cancellations' => [[self::BRANCH]],
        'banquet_payments' => [[self::BRANCH]],
        'banquet_payment_entries' => [[self::BRANCH]],
        'banquet_payment_reminders' => [[self::VIA, 'banquet_payment_id', 'banquet_payments']],
        'banquet_settlements' => [[self::BRANCH]],
        'banquet_extras' => [[self::STORE_BRANCHES]],
        'banquet_hall_rates' => [[self::STORE_BRANCHES]],
        'banquet_packages' => [[self::STORE_BRANCHES]],
        'banquet_package_items' => [[self::VIA, 'banquet_package_id', 'banquet_packages']],
        'banquet_settings' => [[self::STORE_BRANCHES]],
        'banquet_ops_settings' => [[self::STORE_BRANCHES]],
        'banquet_payment_settings' => [[self::STORE_BRANCHES]],

        // Tips, staff pay, vouchers, customer accounts
        'pos_tip_settings' => [[self::BRANCH]],
        'pos_tip_distributions' => [[self::BRANCH]],
        'pos_tip_distribution_lines' => [[self::VIA, 'distribution_id', 'pos_tip_distributions']],
        'staff_pay_rules' => [[self::BRANCH]],
        'staff_pay_settings' => [[self::BRANCH]],
        'staff_pay_statements' => [[self::BRANCH]],
        'staff_pay_statement_lines' => [[self::VIA, 'statement_id', 'staff_pay_statements']],
        'vouchers' => [[self::BRANCH]],
        'voucher_entries' => [[self::BRANCH]],
        'customer_accounts' => [[self::BRANCH]],
        'customer_account_entries' => [[self::BRANCH]],
        'customer_account_members' => [[self::STORE_BRANCHES]],

        // Menu, stop-list and availability: marketplace-visible, so the store token (reaches every branch too)
        'products' => [[self::STORE]],
        'product_variants' => [[self::VIA, 'product_id', 'products']],
        'product_modifier_groups' => [[self::VIA, 'product_id', 'products']],
        'product_ingredients' => [[self::VIA, 'product_id', 'products']],
        'modifier_groups' => [[self::STORE]],
        'modifiers' => [[self::VIA, 'modifier_group_id', 'modifier_groups']],
        'combos' => [[self::STORE]],
        'combo_slots' => [[self::VIA, 'combo_id', 'combos']],
        'combo_slot_options' => [[self::VIA, 'combo_slot_id', 'combo_slots']],
        'stop_list_entries' => [[self::STORE]],
        'ingredients' => [[self::STORE]],
        'branch_product_variants' => [[self::VIA, 'branch_id', 'branches']],
        'branch_product_rotation' => [[self::VIA, 'branch_id', 'branches']],
        'daily_menus' => [[self::STORE]],
        'daily_menu_items' => [[self::VIA, 'daily_menu_id', 'daily_menus']],
        'daily_sets' => [[self::STORE]],
        'daily_set_items' => [[self::VIA, 'daily_set_id', 'daily_sets']],
        'menu_batches' => [[self::STORE]],
        'menu_batch_plans' => [[self::STORE]],
        'menu_batch_writeoffs' => [[self::STORE]],
        'menu_price_history' => [[self::NONE, 'append-only price log; the product/variant row it records is touched itself']],
        'channel_prices' => [[self::STORE]],
        'brands' => [[self::STORE]],
        'branch_brands' => [[self::STORE]],
        'brand_channels' => [[self::STORE]],
        'promos' => [[self::STORE]],
        'promo_codes' => [[self::STORE]],

        // Store and branch settings (opening state, fees, takeaway, delivery): marketplace-visible
        'stores' => [[self::STORE, 'id']],
        'branches' => [[self::STORE]],
        'delivery_zones' => [[self::STORE]],
        'service_fee_settings' => [[self::STORE]],
        'branch_takeaway_settings' => [[self::STORE]],
        'branch_packaging_rules' => [[self::STORE]],
        'store_subscriptions' => [[self::STORE]],

        // Branch POS configuration (devices, printers, kitchen routing, labels, LAN, booking rules)
        'devices' => [[self::BRANCH]],
        'printers' => [[self::BRANCH]],
        'kitchen_stations' => [[self::BRANCH]],
        'kitchen_station_routes' => [[self::BRANCH]],
        'branch_label_settings' => [[self::BRANCH]],
        'branch_lan_settings' => [[self::BRANCH]],
        'branch_booking_rules' => [[self::BRANCH]],
        'branch_terminal_setups' => [[self::BRANCH]],
        'pos_qr_settings' => [[self::BRANCH]],
        'auto_guest_items' => [[self::BRANCH]],
        'integration_connections' => [[self::BRANCH]],
        'role_permission_overrides' => [[self::STORE_BRANCHES]],
        'channel_commissions' => [[self::STORE_BRANCHES]],
        'users' => [[self::STORE_BRANCHES], [self::USER, 'id']],
        'store_transactions' => [[self::STORE_BRANCHES]],
        'store_wallet_transactions' => [[self::STORE_BRANCHES]],
        'subscription_invoices' => [[self::STORE_BRANCHES]],

        // Platform-wide reference data the stores' screens show (rare writes by the RIZQIM team)
        'categories' => [[self::ALL_STORES]],
        'regions' => [[self::ALL_STORES]],
        'districts' => [[self::ALL_STORES]],
        'settings' => [[self::ALL_STORES]],
        'features' => [[self::FEATURE_SCOPE, 'scope']],
        'tariff_plans' => [[self::ALL_STORES]],
        'payment_providers' => [[self::ALL_STORES]],

        // Not shown on any polled or live screen of a store, branch or customer
        'admin_login_challenges' => [[self::NONE, 'RIZQIM team sign-in challenges']],
        'alert_rules' => [[self::NONE, 'RIZQIM team monitoring rules']],
        'alerts' => [[self::NONE, 'RIZQIM team monitoring alerts (admin screens are not revision-scoped)']],
        'audit_logs' => [[self::NONE, 'append-only audit log']],
        'cache' => [[self::NONE, 'framework cache table']],
        'cache_locks' => [[self::NONE, 'framework cache locks']],
        'device_pairing_codes' => [[self::NONE, 'one-time pairing codes; the device row created by pairing is touched']],
        'external_refs' => [[self::NONE, 'integration id mapping; the orders it maps are touched themselves']],
        'failed_jobs' => [[self::NONE, 'queue bookkeeping']],
        'finance_adjustments' => [[self::NONE, 'RIZQIM finance (admin)']],
        'idempotency_keys' => [[self::NONE, 'idempotency replay cache']],
        'integration_events' => [[self::NONE, 'integration delivery log']],
        'job_batches' => [[self::NONE, 'queue bookkeeping']],
        'jobs' => [[self::NONE, 'queue bookkeeping']],
        'migrations' => [[self::NONE, 'schema bookkeeping']],
        'monitoring_markers' => [[self::NONE, 'RIZQIM team monitoring']],
        'password_reset_tokens' => [[self::NONE, 'auth']],
        'payout_requests' => [[self::NONE, 'RIZQIM finance (admin)']],
        'personal_access_tokens' => [[self::NONE, 'auth tokens (last_used_at is written on requests)']],
        'phone_verifications' => [[self::NONE, 'auth']],
        'platform_daily_stats' => [[self::NONE, 'RIZQIM team statistics']],
        'platform_hourly_stats' => [[self::NONE, 'RIZQIM team statistics']],
        'platform_stats_days' => [[self::NONE, 'RIZQIM team statistics']],
        'platform_invites' => [[self::NONE, 'RIZQIM team invites']],
        'platform_role_permissions' => [[self::NONE, 'RIZQIM team permissions']],
        'refresh_tokens' => [[self::NONE, 'auth']],
        'sessions' => [[self::NONE, 'web sessions']],
        'store_applications' => [[self::NONE, 'onboarding applications (admin)']],
        'store_broadcasts' => [[self::NONE, 'RIZQIM marketing broadcasts (admin)']],
        'store_broadcast_recipients' => [[self::NONE, 'RIZQIM marketing broadcasts (admin)']],
        'store_crm_profiles' => [[self::NONE, 'RIZQIM CRM (admin)']],
        'store_health_scores' => [[self::NONE, 'RIZQIM CRM (admin)']],
        'store_notes' => [[self::NONE, 'RIZQIM CRM (admin)']],
        'store_status_logs' => [[self::NONE, 'append-only log; the stores row it records is touched itself']],
        'store_tasks' => [[self::NONE, 'RIZQIM CRM (admin)']],
        'store_topup_requests' => [[self::NONE, 'RIZQIM finance (admin)']],
        'subscription_dunning_events' => [[self::NONE, 'billing log; the subscription row it acts on is touched itself']],
        'support_canned_replies' => [[self::NONE, 'RIZQIM support templates']],
    ];

    /** @return list<list<string>>|null */
    public static function rules(string $table): ?array
    {
        return self::TABLES[$table] ?? null;
    }

    /** True when a write to the table can move a token */
    public static function tracks(string $table): bool
    {
        $rules = self::TABLES[$table] ?? null;

        return $rules !== null && $rules[0][0] !== self::NONE;
    }
}
