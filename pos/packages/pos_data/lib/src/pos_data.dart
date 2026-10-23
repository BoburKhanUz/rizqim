import 'dart:async';
import 'dart:math';

import 'package:pos_sync/pos_sync.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'accounts/account_repository.dart';
import 'billing/billing_repository.dart';
import 'bookings/booking_repository.dart';
import 'bootstrap/bootstrap_repository.dart';
import 'catalog/catalog_repository.dart';
import 'floor/floor_repository.dart';
import 'http/connectivity.dart';
import 'http/pos_http.dart';
import 'offline/pos_offline.dart';
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
///
/// P3-31: [offlineStore] berilsa (kassa) — internetsiz cheklangan rejim ([offline], docs/POS_SPEC.md 5-bo'lim):
/// aloqa yo'qolganda buyurtma, bosqich, yuborish, schyot va to'lov diskdagi navbatga yoziladi va aloqa qaytgach
/// `POST /pos/sync` ga tartib bilan yuboriladi. Berilmasa (ofitsiant) — avvalgidek: aloqa yo'q bo'lsa faqat ko'rish.
class PosData {
  PosData(
    this.client, {
    PosCatalogCache? catalogCache,
    DateTime Function()? clock,
    Random? random,
    SyncStore? offlineStore,
    SyncTransport? syncTransport,
  }) : http = PosHttp(
         client.dio,
         connectivity: PosConnectivity(clock: clock),
         random: random,
       ) {
    offline = offlineStore == null
        ? null
        : PosOffline(
            store: offlineStore,
            http: http,
            clock: clock,
            random: random,
            transport: syncTransport,
          );
    bootstrap = PosBootstrapRepository(http, clock: clock)..offline = offline;
    approvals = ApprovalService(http);
    session = PosSession(
      auth: client.auth,
      bootstrapRepository: bootstrap,
      approvals: approvals,
    );
    catalog = PosCatalogRepository(http, cache: catalogCache, clock: clock)
      ..offline = offline;
    floor = FloorRepository(http, offline: offline);
    orders = PosOrderRepository(http, offline: offline);
    billing = BillingRepository(http, offline: offline);
    shifts = ShiftRepository(http, offline: offline);
    bookings = BookingRepository(http);
    accounts = CustomerAccountRepository(http);
    final PosOffline? o = offline;
    if (o != null) {
      o
        ..staffId = (() => session.staffId)
        ..staffName = (() => session.staffName)
        ..can = session.can
        ..shiftUuid = (() => session.shift?.uuid)
        ..bootstrap = (() => session.bootstrap ?? o.storedBootstrap())
        ..catalog = catalog.cached;
      // The journal and copies belong to this pairing: a re-paired till must not send them with its new token
      _authSub = client.auth.events.listen((AuthEvent e) {
        if (e.kind == AuthEventKind.terminalRevoked ||
            e.kind == AuthEventKind.terminalForgotten) {
          unawaited(o.forget());
        }
      });
    }
  }

  StreamSubscription<AuthEvent>? _authSub;

  /// Internetsiz cheklangan rejim (kassa); ofitsiantda `null`.
  late final PosOffline? offline;

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
    await _authSub?.cancel();
    await offline?.dispose();
    await session.dispose();
    await catalog.dispose();
    await connectivity.dispose();
  }
}
