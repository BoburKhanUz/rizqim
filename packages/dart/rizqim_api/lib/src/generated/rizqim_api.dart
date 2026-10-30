// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';

import 'broadcast/broadcast_api.dart';
import 'addresses/addresses_api.dart';
import 'auth/auth_api.dart';
import 'catalog/catalog_api.dart';
import 'complaints/complaints_api.dart';
import 'couriers/couriers_api.dart';
import 'delivery/delivery_api.dart';
import 'devices/devices_api.dart';
import 'terminal/terminal_api.dart';
import 'feature_flags/feature_flags_api.dart';
import 'geocoding/geocoding_api.dart';
import 'health/health_api.dart';
import 'locations/locations_api.dart';
import 'notifications/notifications_api.dart';
import 'orders/orders_api.dart';
import 'owner_analytics/owner_analytics_api.dart';
import 'owner_audit_log/owner_audit_log_api.dart';
import 'owner_auto_guest_items/owner_auto_guest_items_api.dart';
import 'owner_batches/owner_batches_api.dart';
import 'owner_billing/owner_billing_api.dart';
import 'owner_branch_availability/owner_branch_availability_api.dart';
import 'owner_branches/owner_branches_api.dart';
import 'owner_fiscal/owner_fiscal_api.dart';
import 'owner_table_booking/owner_table_booking_api.dart';
import 'owner_pos_settings/owner_pos_settings_api.dart';
import 'owner_catalog/owner_catalog_api.dart';
import 'owner_daily_menu/owner_daily_menu_api.dart';
import 'owner_delivery_zones/owner_delivery_zones_api.dart';
import 'owner_devices/owner_devices_api.dart';
import 'owner_halls_and_tables/owner_halls_and_tables_api.dart';
import 'owner_employees/owner_employees_api.dart';
import 'owner_finance/owner_finance_api.dart';
import 'owner_finance_history/owner_finance_history_api.dart';
import 'owner_kitchen_stations/owner_kitchen_stations_api.dart';
import 'owner_menu_modifiers/owner_menu_modifiers_api.dart';
import 'owner_staff_access/owner_staff_access_api.dart';
import 'owner_pos_format/owner_pos_format_api.dart';
import 'owner_service_charge_rules/owner_service_charge_rules_api.dart';
import 'owner_tips/owner_tips_api.dart';
import 'owner_printers/owner_printers_api.dart';
import 'owner_channel_prices/owner_channel_prices_api.dart';
import 'owner_menu/owner_menu_api.dart';
import 'owner_reports/owner_reports_api.dart';
import 'owner_room_tariffs/owner_room_tariffs_api.dart';
import 'owner_service_fees/owner_service_fees_api.dart';
import 'owner_settings/owner_settings_api.dart';
import 'owner_staff/owner_staff_api.dart';
import 'owner_staff_pay/owner_staff_pay_api.dart';
import 'owner_stop_list/owner_stop_list_api.dart';
import 'pos_billing/pos_billing_api.dart';
import 'pos_bookings/pos_bookings_api.dart';
import 'pos_bootstrap/pos_bootstrap_api.dart';
import 'pos_staff_pay/pos_staff_pay_api.dart';
import 'pos_customer_accounts/pos_customer_accounts_api.dart';
import 'pos_customer_display/pos_customer_display_api.dart';
import 'pos_orders/pos_orders_api.dart';
import 'pos_queue_numbers/pos_queue_numbers_api.dart';
import 'pos_sync/pos_sync_api.dart';
import 'pos_hostess/pos_hostess_api.dart';
import 'pos_tips/pos_tips_api.dart';
import 'promo_codes/promo_codes_api.dart';
import 'reports/reports_api.dart';
import 'kitchen/kitchen_api.dart';
import 'staff_orders/staff_orders_api.dart';
import 'ovqatxona_arizasi/ovqatxona_arizasi_api.dart';
import 'stores/stores_api.dart';
import 'fallback/fallback_api.dart';

/// Rizqim API `v1.0.0`.
///
/// Rizqim backend API (`/api/v1`): Telegram Mini App, ega/menejer/kuryer ekranlari va to'lov webhooklari.
///
/// **Kirish:** `Authorization: Bearer <token>` (Laravel Sanctum). Token `POST /v1/auth/telegram` orqali olinadi.
///
/// **Javob formati:** `{ "success": bool, "message": string, "data": ... }`. Xatolarda `success: false` va `message`;.
/// validatsiya xatolari (422) `errors` maydonida keladi.
///
/// Spetsifikatsiya koddan yaratiladi: `cd backend && php artisan api-spec:export` (`packages/api-spec/openapi.json`).
class RizqimApi {
  RizqimApi(Dio dio, {String? baseUrl}) : _dio = dio, _baseUrl = baseUrl;

  final Dio _dio;
  final String? _baseUrl;

  static String get version => '1.0.0';

  BroadcastApi? _broadcast;
  AddressesApi? _addresses;
  AuthApi? _auth;
  CatalogApi? _catalog;
  ComplaintsApi? _complaints;
  CouriersApi? _couriers;
  DeliveryApi? _delivery;
  DevicesApi? _devices;
  TerminalApi? _terminal;
  FeatureFlagsApi? _featureFlags;
  GeocodingApi? _geocoding;
  HealthApi? _health;
  LocationsApi? _locations;
  NotificationsApi? _notifications;
  OrdersApi? _orders;
  OwnerAnalyticsApi? _ownerAnalytics;
  OwnerAuditLogApi? _ownerAuditLog;
  OwnerAutoGuestItemsApi? _ownerAutoGuestItems;
  OwnerBatchesApi? _ownerBatches;
  OwnerBillingApi? _ownerBilling;
  OwnerBranchAvailabilityApi? _ownerBranchAvailability;
  OwnerBranchesApi? _ownerBranches;
  OwnerFiscalApi? _ownerFiscal;
  OwnerTableBookingApi? _ownerTableBooking;
  OwnerPosSettingsApi? _ownerPosSettings;
  OwnerCatalogApi? _ownerCatalog;
  OwnerDailyMenuApi? _ownerDailyMenu;
  OwnerDeliveryZonesApi? _ownerDeliveryZones;
  OwnerDevicesApi? _ownerDevices;
  OwnerHallsAndTablesApi? _ownerHallsAndTables;
  OwnerEmployeesApi? _ownerEmployees;
  OwnerFinanceApi? _ownerFinance;
  OwnerFinanceHistoryApi? _ownerFinanceHistory;
  OwnerKitchenStationsApi? _ownerKitchenStations;
  OwnerMenuModifiersApi? _ownerMenuModifiers;
  OwnerStaffAccessApi? _ownerStaffAccess;
  OwnerPosFormatApi? _ownerPosFormat;
  OwnerServiceChargeRulesApi? _ownerServiceChargeRules;
  OwnerTipsApi? _ownerTips;
  OwnerPrintersApi? _ownerPrinters;
  OwnerChannelPricesApi? _ownerChannelPrices;
  OwnerMenuApi? _ownerMenu;
  OwnerReportsApi? _ownerReports;
  OwnerRoomTariffsApi? _ownerRoomTariffs;
  OwnerServiceFeesApi? _ownerServiceFees;
  OwnerSettingsApi? _ownerSettings;
  OwnerStaffApi? _ownerStaff;
  OwnerStaffPayApi? _ownerStaffPay;
  OwnerStopListApi? _ownerStopList;
  PosBillingApi? _posBilling;
  PosBookingsApi? _posBookings;
  PosBootstrapApi? _posBootstrap;
  PosStaffPayApi? _posStaffPay;
  PosCustomerAccountsApi? _posCustomerAccounts;
  PosCustomerDisplayApi? _posCustomerDisplay;
  PosOrdersApi? _posOrders;
  PosQueueNumbersApi? _posQueueNumbers;
  PosSyncApi? _posSync;
  PosHostessApi? _posHostess;
  PosTipsApi? _posTips;
  PromoCodesApi? _promoCodes;
  ReportsApi? _reports;
  KitchenApi? _kitchen;
  StaffOrdersApi? _staffOrders;
  OvqatxonaArizasiApi? _ovqatxonaArizasi;
  StoresApi? _stores;
  FallbackApi? _fallback;

  BroadcastApi get broadcast =>
      _broadcast ??= BroadcastApi(_dio, baseUrl: _baseUrl);

  AddressesApi get addresses =>
      _addresses ??= AddressesApi(_dio, baseUrl: _baseUrl);

  AuthApi get auth => _auth ??= AuthApi(_dio, baseUrl: _baseUrl);

  CatalogApi get catalog => _catalog ??= CatalogApi(_dio, baseUrl: _baseUrl);

  ComplaintsApi get complaints =>
      _complaints ??= ComplaintsApi(_dio, baseUrl: _baseUrl);

  CouriersApi get couriers =>
      _couriers ??= CouriersApi(_dio, baseUrl: _baseUrl);

  DeliveryApi get delivery =>
      _delivery ??= DeliveryApi(_dio, baseUrl: _baseUrl);

  DevicesApi get devices => _devices ??= DevicesApi(_dio, baseUrl: _baseUrl);

  TerminalApi get terminal =>
      _terminal ??= TerminalApi(_dio, baseUrl: _baseUrl);

  FeatureFlagsApi get featureFlags =>
      _featureFlags ??= FeatureFlagsApi(_dio, baseUrl: _baseUrl);

  GeocodingApi get geocoding =>
      _geocoding ??= GeocodingApi(_dio, baseUrl: _baseUrl);

  HealthApi get health => _health ??= HealthApi(_dio, baseUrl: _baseUrl);

  LocationsApi get locations =>
      _locations ??= LocationsApi(_dio, baseUrl: _baseUrl);

  NotificationsApi get notifications =>
      _notifications ??= NotificationsApi(_dio, baseUrl: _baseUrl);

  OrdersApi get orders => _orders ??= OrdersApi(_dio, baseUrl: _baseUrl);

  OwnerAnalyticsApi get ownerAnalytics =>
      _ownerAnalytics ??= OwnerAnalyticsApi(_dio, baseUrl: _baseUrl);

  OwnerAuditLogApi get ownerAuditLog =>
      _ownerAuditLog ??= OwnerAuditLogApi(_dio, baseUrl: _baseUrl);

  OwnerAutoGuestItemsApi get ownerAutoGuestItems =>
      _ownerAutoGuestItems ??= OwnerAutoGuestItemsApi(_dio, baseUrl: _baseUrl);

  OwnerBatchesApi get ownerBatches =>
      _ownerBatches ??= OwnerBatchesApi(_dio, baseUrl: _baseUrl);

  OwnerBillingApi get ownerBilling =>
      _ownerBilling ??= OwnerBillingApi(_dio, baseUrl: _baseUrl);

  OwnerBranchAvailabilityApi get ownerBranchAvailability =>
      _ownerBranchAvailability ??= OwnerBranchAvailabilityApi(
        _dio,
        baseUrl: _baseUrl,
      );

  OwnerBranchesApi get ownerBranches =>
      _ownerBranches ??= OwnerBranchesApi(_dio, baseUrl: _baseUrl);

  OwnerFiscalApi get ownerFiscal =>
      _ownerFiscal ??= OwnerFiscalApi(_dio, baseUrl: _baseUrl);

  OwnerTableBookingApi get ownerTableBooking =>
      _ownerTableBooking ??= OwnerTableBookingApi(_dio, baseUrl: _baseUrl);

  OwnerPosSettingsApi get ownerPosSettings =>
      _ownerPosSettings ??= OwnerPosSettingsApi(_dio, baseUrl: _baseUrl);

  OwnerCatalogApi get ownerCatalog =>
      _ownerCatalog ??= OwnerCatalogApi(_dio, baseUrl: _baseUrl);

  OwnerDailyMenuApi get ownerDailyMenu =>
      _ownerDailyMenu ??= OwnerDailyMenuApi(_dio, baseUrl: _baseUrl);

  OwnerDeliveryZonesApi get ownerDeliveryZones =>
      _ownerDeliveryZones ??= OwnerDeliveryZonesApi(_dio, baseUrl: _baseUrl);

  OwnerDevicesApi get ownerDevices =>
      _ownerDevices ??= OwnerDevicesApi(_dio, baseUrl: _baseUrl);

  OwnerHallsAndTablesApi get ownerHallsAndTables =>
      _ownerHallsAndTables ??= OwnerHallsAndTablesApi(_dio, baseUrl: _baseUrl);

  OwnerEmployeesApi get ownerEmployees =>
      _ownerEmployees ??= OwnerEmployeesApi(_dio, baseUrl: _baseUrl);

  OwnerFinanceApi get ownerFinance =>
      _ownerFinance ??= OwnerFinanceApi(_dio, baseUrl: _baseUrl);

  OwnerFinanceHistoryApi get ownerFinanceHistory =>
      _ownerFinanceHistory ??= OwnerFinanceHistoryApi(_dio, baseUrl: _baseUrl);

  OwnerKitchenStationsApi get ownerKitchenStations => _ownerKitchenStations ??=
      OwnerKitchenStationsApi(_dio, baseUrl: _baseUrl);

  OwnerMenuModifiersApi get ownerMenuModifiers =>
      _ownerMenuModifiers ??= OwnerMenuModifiersApi(_dio, baseUrl: _baseUrl);

  OwnerStaffAccessApi get ownerStaffAccess =>
      _ownerStaffAccess ??= OwnerStaffAccessApi(_dio, baseUrl: _baseUrl);

  OwnerPosFormatApi get ownerPosFormat =>
      _ownerPosFormat ??= OwnerPosFormatApi(_dio, baseUrl: _baseUrl);

  OwnerServiceChargeRulesApi get ownerServiceChargeRules =>
      _ownerServiceChargeRules ??= OwnerServiceChargeRulesApi(
        _dio,
        baseUrl: _baseUrl,
      );

  OwnerTipsApi get ownerTips =>
      _ownerTips ??= OwnerTipsApi(_dio, baseUrl: _baseUrl);

  OwnerPrintersApi get ownerPrinters =>
      _ownerPrinters ??= OwnerPrintersApi(_dio, baseUrl: _baseUrl);

  OwnerChannelPricesApi get ownerChannelPrices =>
      _ownerChannelPrices ??= OwnerChannelPricesApi(_dio, baseUrl: _baseUrl);

  OwnerMenuApi get ownerMenu =>
      _ownerMenu ??= OwnerMenuApi(_dio, baseUrl: _baseUrl);

  OwnerReportsApi get ownerReports =>
      _ownerReports ??= OwnerReportsApi(_dio, baseUrl: _baseUrl);

  OwnerRoomTariffsApi get ownerRoomTariffs =>
      _ownerRoomTariffs ??= OwnerRoomTariffsApi(_dio, baseUrl: _baseUrl);

  OwnerServiceFeesApi get ownerServiceFees =>
      _ownerServiceFees ??= OwnerServiceFeesApi(_dio, baseUrl: _baseUrl);

  OwnerSettingsApi get ownerSettings =>
      _ownerSettings ??= OwnerSettingsApi(_dio, baseUrl: _baseUrl);

  OwnerStaffApi get ownerStaff =>
      _ownerStaff ??= OwnerStaffApi(_dio, baseUrl: _baseUrl);

  OwnerStaffPayApi get ownerStaffPay =>
      _ownerStaffPay ??= OwnerStaffPayApi(_dio, baseUrl: _baseUrl);

  OwnerStopListApi get ownerStopList =>
      _ownerStopList ??= OwnerStopListApi(_dio, baseUrl: _baseUrl);

  PosBillingApi get posBilling =>
      _posBilling ??= PosBillingApi(_dio, baseUrl: _baseUrl);

  PosBookingsApi get posBookings =>
      _posBookings ??= PosBookingsApi(_dio, baseUrl: _baseUrl);

  PosBootstrapApi get posBootstrap =>
      _posBootstrap ??= PosBootstrapApi(_dio, baseUrl: _baseUrl);

  PosStaffPayApi get posStaffPay =>
      _posStaffPay ??= PosStaffPayApi(_dio, baseUrl: _baseUrl);

  PosCustomerAccountsApi get posCustomerAccounts =>
      _posCustomerAccounts ??= PosCustomerAccountsApi(_dio, baseUrl: _baseUrl);

  PosCustomerDisplayApi get posCustomerDisplay =>
      _posCustomerDisplay ??= PosCustomerDisplayApi(_dio, baseUrl: _baseUrl);

  PosOrdersApi get posOrders =>
      _posOrders ??= PosOrdersApi(_dio, baseUrl: _baseUrl);

  PosQueueNumbersApi get posQueueNumbers =>
      _posQueueNumbers ??= PosQueueNumbersApi(_dio, baseUrl: _baseUrl);

  PosSyncApi get posSync => _posSync ??= PosSyncApi(_dio, baseUrl: _baseUrl);

  PosHostessApi get posHostess =>
      _posHostess ??= PosHostessApi(_dio, baseUrl: _baseUrl);

  PosTipsApi get posTips => _posTips ??= PosTipsApi(_dio, baseUrl: _baseUrl);

  PromoCodesApi get promoCodes =>
      _promoCodes ??= PromoCodesApi(_dio, baseUrl: _baseUrl);

  ReportsApi get reports => _reports ??= ReportsApi(_dio, baseUrl: _baseUrl);

  KitchenApi get kitchen => _kitchen ??= KitchenApi(_dio, baseUrl: _baseUrl);

  StaffOrdersApi get staffOrders =>
      _staffOrders ??= StaffOrdersApi(_dio, baseUrl: _baseUrl);

  OvqatxonaArizasiApi get ovqatxonaArizasi =>
      _ovqatxonaArizasi ??= OvqatxonaArizasiApi(_dio, baseUrl: _baseUrl);

  StoresApi get stores => _stores ??= StoresApi(_dio, baseUrl: _baseUrl);

  FallbackApi get fallback =>
      _fallback ??= FallbackApi(_dio, baseUrl: _baseUrl);
}
