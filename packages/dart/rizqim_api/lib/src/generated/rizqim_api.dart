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
import 'owner_batches/owner_batches_api.dart';
import 'owner_branch_availability/owner_branch_availability_api.dart';
import 'owner_catalog/owner_catalog_api.dart';
import 'owner_devices/owner_devices_api.dart';
import 'owner_employees/owner_employees_api.dart';
import 'owner_finance/owner_finance_api.dart';
import 'owner_billing/owner_billing_api.dart';
import 'owner_menu_modifiers/owner_menu_modifiers_api.dart';
import 'owner_staff_access/owner_staff_access_api.dart';
import 'owner_channel_prices/owner_channel_prices_api.dart';
import 'owner_service_fees/owner_service_fees_api.dart';
import 'owner_settings/owner_settings_api.dart';
import 'owner_stop_list/owner_stop_list_api.dart';
import 'promo_codes/promo_codes_api.dart';
import 'reports/reports_api.dart';
import 'stores/stores_api.dart';
import 'superadmin/superadmin_api.dart';
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
  OwnerBatchesApi? _ownerBatches;
  OwnerBranchAvailabilityApi? _ownerBranchAvailability;
  OwnerCatalogApi? _ownerCatalog;
  OwnerDevicesApi? _ownerDevices;
  OwnerEmployeesApi? _ownerEmployees;
  OwnerFinanceApi? _ownerFinance;
  OwnerBillingApi? _ownerBilling;
  OwnerMenuModifiersApi? _ownerMenuModifiers;
  OwnerStaffAccessApi? _ownerStaffAccess;
  OwnerChannelPricesApi? _ownerChannelPrices;
  OwnerServiceFeesApi? _ownerServiceFees;
  OwnerSettingsApi? _ownerSettings;
  OwnerStopListApi? _ownerStopList;
  PromoCodesApi? _promoCodes;
  ReportsApi? _reports;
  StoresApi? _stores;
  SuperadminApi? _superadmin;
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

  OwnerBatchesApi get ownerBatches =>
      _ownerBatches ??= OwnerBatchesApi(_dio, baseUrl: _baseUrl);

  OwnerBranchAvailabilityApi get ownerBranchAvailability =>
      _ownerBranchAvailability ??= OwnerBranchAvailabilityApi(
        _dio,
        baseUrl: _baseUrl,
      );

  OwnerCatalogApi get ownerCatalog =>
      _ownerCatalog ??= OwnerCatalogApi(_dio, baseUrl: _baseUrl);

  OwnerDevicesApi get ownerDevices =>
      _ownerDevices ??= OwnerDevicesApi(_dio, baseUrl: _baseUrl);

  OwnerEmployeesApi get ownerEmployees =>
      _ownerEmployees ??= OwnerEmployeesApi(_dio, baseUrl: _baseUrl);

  OwnerFinanceApi get ownerFinance =>
      _ownerFinance ??= OwnerFinanceApi(_dio, baseUrl: _baseUrl);

  OwnerBillingApi get ownerBilling =>
      _ownerBilling ??= OwnerBillingApi(_dio, baseUrl: _baseUrl);

  OwnerMenuModifiersApi get ownerMenuModifiers =>
      _ownerMenuModifiers ??= OwnerMenuModifiersApi(_dio, baseUrl: _baseUrl);

  OwnerStaffAccessApi get ownerStaffAccess =>
      _ownerStaffAccess ??= OwnerStaffAccessApi(_dio, baseUrl: _baseUrl);

  OwnerChannelPricesApi get ownerChannelPrices =>
      _ownerChannelPrices ??= OwnerChannelPricesApi(_dio, baseUrl: _baseUrl);

  OwnerServiceFeesApi get ownerServiceFees =>
      _ownerServiceFees ??= OwnerServiceFeesApi(_dio, baseUrl: _baseUrl);

  OwnerSettingsApi get ownerSettings =>
      _ownerSettings ??= OwnerSettingsApi(_dio, baseUrl: _baseUrl);

  OwnerStopListApi get ownerStopList =>
      _ownerStopList ??= OwnerStopListApi(_dio, baseUrl: _baseUrl);

  PromoCodesApi get promoCodes =>
      _promoCodes ??= PromoCodesApi(_dio, baseUrl: _baseUrl);

  ReportsApi get reports => _reports ??= ReportsApi(_dio, baseUrl: _baseUrl);

  StoresApi get stores => _stores ??= StoresApi(_dio, baseUrl: _baseUrl);

  SuperadminApi get superadmin =>
      _superadmin ??= SuperadminApi(_dio, baseUrl: _baseUrl);

  FallbackApi get fallback =>
      _fallback ??= FallbackApi(_dio, baseUrl: _baseUrl);
}
