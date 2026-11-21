import 'dart:async';
import 'dart:math';

import 'package:pos_sync/pos_sync.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'accounts/account_repository.dart';
import 'banquets/banquet_ops_repository.dart';
import 'banquets/banquet_payment_repository.dart';
import 'banquets/banquet_repository.dart';
import 'billing/billing_repository.dart';
import 'bookings/booking_repository.dart';
import 'bootstrap/bootstrap_repository.dart';
import 'catalog/catalog_repository.dart';
import 'display/display_feed.dart';
import 'display/display_repository.dart';
import 'floor/floor_repository.dart';
import 'hostess/hostess_repository.dart';
import 'ingredients/ingredient_repository.dart';
import 'http/connectivity.dart';
import 'http/pos_http.dart';
import 'lan/pos_lan_route.dart';
import 'menu_day/batch_repository.dart';
import 'offline/pos_offline.dart';
import 'offline/sync_keys.dart';
import 'orders/order_repository.dart';
import 'realtime/pos_events.dart';
import 'session/approvals.dart';
import 'session/pos_session.dart';
import 'shift/shift_repository.dart';
import 'staffpay/staff_pay_repository.dart';
import 'qr/qr_payment_repository.dart';
import 'tips/tip_repository.dart';
import 'vouchers/voucher_repository.dart';

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
            // M5 S1 review: the journal is checked against the terminal restored on this device before the sender
            // starts (the app restores the saved terminal before it builds PosData)
            device: () => client.auth.terminal?.device.uuid,
          );
    bootstrap = PosBootstrapRepository(http, clock: clock)..offline = offline;
    approvals = ApprovalService(http);
    final PosOffline? till = offline;
    session = PosSession(
      auth: client.auth,
      bootstrapRepository: bootstrap,
      approvals: approvals,
      // M5 S6-a: "Davom etish" after a restart during an outage, on the till only, and only while its server said it
      // records the mark (`offline.staff_resume`, read from the kept bootstrap copy)
      connectivity: http.connectivity,
      resumeSupported: () => till?.settings.staffResume ?? false,
      lastKnownTime: till == null ? null : () => till.latestKnownTime,
      // M5 S6 (review 2 HIGH): the open shift is kept on the till at every change, read back after a restart
      shiftChanged: till?.rememberShift,
    );
    catalog = PosCatalogRepository(http, cache: catalogCache, clock: clock)
      ..offline = offline;
    floor = FloorRepository(http, offline: offline);
    orders = PosOrderRepository(http, offline: offline);
    billing = BillingRepository(http, offline: offline);
    shifts = ShiftRepository(http, offline: offline);
    bookings = BookingRepository(http);
    accounts = CustomerAccountRepository(http);
    display = PosDisplayRepository(http);
    displayFeed = DisplayFeed(display);
    batches = PosBatchRepository(http);
    hostess = HostessRepository(http);
    staffClock = StaffClockRepository(http);
    tips = PosTipsRepository(http);
    vouchers = PosVoucherRepository(http, offline: offline);
    qr = PosQrPaymentRepository(http, offline: offline);
    banquets = PosBanquetRepository(http, offline: offline);
    banquetOps = PosBanquetOpsRepository(http, offline: offline);
    banquetPayments = PosBanquetPaymentRepository(http, offline: offline);
    ingredients = PosIngredientRepository(http);
    final PosOffline? o = offline;
    // M5 S3: the journal signs with the key the terminal holds, while the server says it signs (asked at every
    // operation; the store was opened by the app before the terminal was known)
    syncKeys = o == null
        ? null
        : PosSyncKeys(auth: client.auth, offline: o, clock: clock);
    if (o != null) {
      o.store.keyProvider ??= syncKeys!.current;
      o
        ..staffId = (() => session.staffId)
        ..staffName = (() => session.staffName)
        ..can = session.can
        ..staffContinued = (() => session.staffContinued)
        ..staffFinishOnly = (() => session.staffFinishOnly)
        ..staffResumed = (() => session.staffResumed)
        ..staffWindowOver = (() => session.staffResumeWindowOver)
        ..shiftUuid = (() => session.shift?.uuid)
        ..bootstrap = (() => session.bootstrap ?? o.storedBootstrap())
        ..catalog = catalog.cached;
      // M5 S1-e: a till with the offline mode has no PIN login without the server, so a staff token that expires
      // during an outage must not sign the staff member out (owner rule: payment is never blocked). The expired token
      // is kept and still sent. M5 S1 review (a flapping link): server contact alone ends nothing. A 401 on the dead
      // token shows the PIN keypad, and when the link is found gone again before a PIN login succeeded, the staff
      // member goes on as before (still restricted)
      client.auth.holdExpiredStaff = true;
      // M5 S6-a: each PIN login on the till is kept (id, name, permissions, time; no PIN, no token) for a restart
      // during an outage (PosSession.resumeOffer)
      client.auth.rememberStaffForResume = true;
      // Owner decision Q1 (21.11.2026): past the 12 h a resumed staff member goes on (payment and closing only, marked)
      // only while the server said it records the mark (`offline.staff_window_over`, the kept bootstrap copy)
      client.auth.resumePastWindow = () => o.settings.staffWindowOver;
      _contactSub = connectivity.changes.listen((PosConnectivityState s) {
        if (!s.online) client.auth.resumeStaffContinuation();
      });
      // The journal and copies belong to this pairing: a re-paired till must not send them with its new token.
      // M5 S1: what the server has not received is never deleted on the way (a 401 on the terminal token, an
      // unpairing): PosOffline.forget sets it aside on the disk first (PosOffline.orphans)
      _authSub = client.auth.events.listen((AuthEvent e) {
        switch (e.kind) {
          case AuthEventKind.terminalRevoked:
            unawaited(o.forget(reason: 'terminal_revoked'));
          case AuthEventKind.terminalForgotten:
            unawaited(o.forget());
          case AuthEventKind.terminalPaired || AuthEventKind.staffSignedIn:
            final String? device = client.auth.terminal?.device.uuid;
            if (device != null) {
              unawaited(
                o.bindDevice(
                  device,
                  paired: e.kind == AuthEventKind.terminalPaired,
                ),
              );
            }
          default:
            break;
        }
      });
    }
  }

  StreamSubscription<AuthEvent>? _authSub;
  StreamSubscription<PosConnectivityState>? _contactSub;

  PosLanRoute? _lan;
  StreamSubscription<PosEvent>? _lanSub;
  final StreamController<PosEvent> _lanEvents =
      StreamController<PosEvent>.broadcast();

  /// P6 lokal ko'prik (ofitsiant ilovasi, `pos_lan` `LanPosRoute`): `lan` rejimida zal, buyurtmalar va ularning
  /// amallari markaz kassaga; schyot va to'lov — faqat kassada. `null` — yo'q (kassa, eski ilova).
  PosLanRoute? get lan => _lan;

  set lan(PosLanRoute? route) {
    if (identical(route, _lan)) return;
    unawaited(_lanSub?.cancel());
    _lan = route;
    floor.lan = route;
    orders.lan = route;
    billing.lan = route;
    _lanSub = route?.events.listen((PosEvent e) {
      if (!_lanEvents.isClosed) _lanEvents.add(e);
    });
  }

  /// Markaz kassa hodisalari server hodisalari shaklida ([PosRefreshScheduler.localEvents]).
  Stream<PosEvent> get lanEvents => _lanEvents.stream;

  /// Internetsiz cheklangan rejim (kassa); ofitsiantda `null`.
  late final PosOffline? offline;

  /// M5 S3: the key of the signed offline queue (the till); `null` where there is no queue.
  late final PosSyncKeys? syncKeys;

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

  /// P6-14: mijoz ekrani (ekran o'qiydi, kassa [displayFeed] orqali chekni yuboradi).
  late final PosDisplayRepository display;
  late final DisplayFeed displayFeed;

  /// P6-15: qozon/partiya hisobdan chiqarish.
  late final PosBatchRepository batches;

  /// P6-24/P6-26: hostes stoli (kutish ro'yxati, stol belgisi), stolni boshqa ofitsiantga berish, qatorga mehmon raqami.
  late final HostessRepository hostess;

  /// P6-23: kirgan xodimning ish vaqti ("Ishga keldim" / "Ishdan ketdim").
  late final StaffClockRepository staffClock;

  /// P6-22: smenaning choy pullari va menejer tuzatishi (choy puli to'lov bilan olinadi).
  late final PosTipsRepository tips;

  /// P6-43: sovg'a sertifikatini sotish, kod bo'yicha qidirish va promo-kod (faqat internet bilan).
  late final PosVoucherRepository vouchers;

  /// P6-41/P6-42: schyot summasiga Click, Payme yoki UzQR dinamik QR (provayder o'zi tasdiqlaydi; internet bilan).
  late final PosQrPaymentRepository qr;

  /// P6-51/P6-52: tadbirlar taqvimi, tadbir kartasi, zalni band qilish va narxni muzlatish (faqat internet bilan).
  late final PosBanquetRepository banquets;

  /// P6-54: ishlab chiqarish varag'i, osh kalkulyatori, bozorlik, shartnoma, tadbir kuni va yakuniy hisob (internet bilan).
  late final PosBanquetOpsRepository banquetOps;

  /// P6-53: tadbir to'lov jadvali, kassada zaklad va oraliq to'lov (bo'nak cheki), bekor qilish (internet bilan).
  late final PosBanquetPaymentRepository banquetPayments;

  /// P6-55 / B9: ingredient bo'yicha tez stop ("Tovuq tugadi"; faqat internet bilan).
  late final PosIngredientRepository ingredients;

  AuthSession get auth => client.auth;
  PosConnectivity get connectivity => http.connectivity;

  Future<void> dispose() async {
    await _authSub?.cancel();
    await _contactSub?.cancel();
    await _lanSub?.cancel();
    await _lanEvents.close();
    await displayFeed.dispose();
    await syncKeys?.dispose();
    await offline?.dispose();
    await session.dispose();
    await catalog.dispose();
    await connectivity.dispose();
  }
}
