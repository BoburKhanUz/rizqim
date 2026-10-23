import 'dart:math';

import 'package:rizqim_api/rizqim_api.dart';

import 'accounts/account_repository.dart';
import 'billing/billing_repository.dart';
import 'bookings/booking_repository.dart';
import 'bootstrap/bootstrap_repository.dart';
import 'catalog/catalog_repository.dart';
import 'floor/floor_repository.dart';
import 'http/connectivity.dart';
import 'http/pos_http.dart';
import 'orders/order_repository.dart';
import 'session/approvals.dart';
import 'session/pos_session.dart';
import 'shift/shift_repository.dart';

/// Kassa va ofitsiant ilovalari uchun bitta kirish nuqtasi: hamma repozitoriy bitta [RizqimClient] (bitta Dio, token
/// va 401 boshqaruvi) ustida.
///
/// ```dart
/// final client = RizqimClient(baseUrl: AppConfig.apiBaseUrl, store: SecureStorageStore());
/// final pos = PosData(client);
/// await client.auth.restore();
/// // PIN bilan kirilgandan keyin:
/// final boot = await pos.session.loadBootstrap();
/// final menu = await pos.catalog.load(PosChannel.hall, menuVersion: boot.menuVersion);
/// final order = await pos.orders.open(channel: PosChannel.hall, tableId: 3, guestsCount: 4);
/// ```
class PosData {
  PosData(
    this.client, {
    PosCatalogCache? catalogCache,
    DateTime Function()? clock,
    Random? random,
  }) : http = PosHttp(
         client.dio,
         connectivity: PosConnectivity(clock: clock),
         random: random,
       ) {
    bootstrap = PosBootstrapRepository(http, clock: clock);
    approvals = ApprovalService(http);
    session = PosSession(
      auth: client.auth,
      bootstrapRepository: bootstrap,
      approvals: approvals,
    );
    catalog = PosCatalogRepository(http, cache: catalogCache, clock: clock);
    floor = FloorRepository(http);
    orders = PosOrderRepository(http);
    billing = BillingRepository(http);
    shifts = ShiftRepository(http);
    bookings = BookingRepository(http);
    accounts = CustomerAccountRepository(http);
  }

  final RizqimClient client;
  final PosHttp http;

  /// Terminal + xodim sessiyasi, bootstrap va smena holati.
  late final PosSession session;
  late final ApprovalService approvals;
  late final PosBootstrapRepository bootstrap;
  late final PosCatalogRepository catalog;
  late final FloorRepository floor;
  late final PosOrderRepository orders;
  late final BillingRepository billing;
  late final ShiftRepository shifts;
  late final BookingRepository bookings;
  late final CustomerAccountRepository accounts;

  AuthSession get auth => client.auth;
  PosConnectivity get connectivity => http.connectivity;

  Future<void> dispose() async {
    await session.dispose();
    await catalog.dispose();
    await connectivity.dispose();
  }
}
