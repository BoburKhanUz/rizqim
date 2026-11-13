import 'dart:async';

import 'package:pos_core/pos_core.dart' show ReceiptHeader;
import 'package:pos_data/pos_data.dart';
import 'package:pos_lan/pos_lan.dart' show LanBillPrints, LanRemoteOp;
import 'package:rizqim_api/rizqim_api.dart';

import 'app_config.dart';
import 'features/billing/qr_offline_guard.dart';
import 'features/settings/cashier_prefs.dart';
import 'hardware/cashier_hardware.dart';
import 'lan/cashier_lan_hub.dart';
import 'lan/lan_bill_prints.dart';
import 'lan/lan_publisher.dart';
import 'printing/bill_print_service.dart';
import 'printing/printing_service.dart';
import 'storage/shell_settings.dart';

/// Ulangan terminal uchun real-time (kalit sozlanmagan yoki terminal filialsiz bo'lsa `null`).
typedef RealtimeFactory = PosRealtime? Function(
  RizqimClient client,
  PairedTerminal terminal,
);

/// Ilovaning tashqi bog'liqliklari (DI): testlarda soxtalari beriladi. Ekranlar ularni `PosScope.of(context)` dan
/// oladi.
class CashierServices {
  CashierServices({
    required this.client,
    required this.settings,
    PosData? data,
    CashierPrinting? printing,
    this.realtimeFactory,
    this.clock,
    this.pollInterval = AppConfig.pollInterval,
    this.livePollInterval = AppConfig.livePollInterval,
    SyncStore? offlineStore,
    CashierHardware? hardware,
    this.lanStore,
    this.billPrintPoll = const Duration(minutes: 1),
    this.qrPollInterval = const Duration(seconds: 3),
  }) : data =
           data ??
           PosData(
             client,
             clock: clock,
             offlineStore: offlineStore,
             // Review P6 wave 4 (C3): a QR pending when cash was taken offline is read again before the payment goes
             syncTransport: offlineStore == null
                 ? null
                 : QrOfflineGuard(clock: clock),
           ),
       printing = printing ?? CashierPrinting(),
       hardware = hardware ?? CashierHardware() {
    final SyncTransport? transport = this.data.offline?.sender.transport;
    if (transport is QrOfflineGuard) qrGuard = transport..attach(this.data);
    // P6 local bridge: from the start (a till reopened offline runs the hub before anyone can sign in)
    unawaited(lan.start());
    // P6 stage B: the till's own cart and queue go to the LAN devices; a bill asked over the LAN offline prints here
    lanPublisher.start();
  }

  /// Review P6 wave 4 (C3): offlayn to'lov va kutilayotgan dinamik QR (`null` — oflayn rejim yo'q yoki [data] tashqaridan).
  QrOfflineGuard? qrGuard;

  /// Review P6 uskunalar/LAN (C2): o'tkazib yuborilgan schyot so'rovlari shu oraliqda ham o'qiladi
  /// ([BillPrintService.catchUp]; real-time sozlanmagan kassa ham chiqaradi). `null` — faqat hodisalarda.
  final Duration? billPrintPoll;

  /// P6 lokal ko'prik kalitlari saqlanadigan xavfsiz xotira (`null`: saqlanmaydi, testlar).
  final SecureKeyValueStore? lanStore;

  /// P6 lokal ko'prik (docs/POS_SPEC.md 5.5): markaz kassa bo'lsa — LAN serveri va beacon.
  late final CashierLanHub lan = CashierLanHub(
    session: data.session,
    auth: client.auth,
    offline: data.offline,
    store: lanStore,
    printsBill: (LanRemoteOp op) async => await lanBills?.decide(op) ?? false,
    // Review P6 S5: a revoked phone or a rotated key reaches the hub without waiting for the next bootstrap
    fetchLan: data.display.lan,
  );

  /// P6 B: kassaning `cart.display` va `queue.*` hodisalari LAN qurilmalariga ([CashierLanHub.publish]).
  late final LanPublisher lanPublisher = LanPublisher(
    hub: lan,
    feed: data.displayFeed,
    offline: data.offline,
  );

  /// P6 B: internetsiz paytda ofitsiant LAN orqali so'ragan schyotni shu kassa chiqaradi (`null`: oflayn rejim yo'q).
  late final LanBillPrints? lanBills = data.offline == null
      ? null
      : tillLanBillPrints(
          offline: data.offline!,
          prints: billPrints,
          now: now,
          autoPrint: () =>
              (session.bootstrap ?? data.offline!.storedBootstrap())
                  ?.branch
                  .settings
                  .autoPrintBill ??
              true,
          printerId: () =>
              (session.bootstrap ?? data.offline!.storedBootstrap())
                  ?.branch
                  .settings
                  .billPrinter,
        );

  final RizqimClient client;

  /// P6 (A bosqichi): ofitsiant so'ragan schyotni (`bill.print_requested`) chek printerida chiqaradi. So'rov
  /// serverdan ulangan terminal ([TerminalHome]: ekran qulflangan paytda ham) va LAN ko'prigidan keladi; o'tkazib
  /// yuborilgani `GET /pos/bill-print-requests` dan ([BillPrintService.catchUp]).
  late final BillPrintService billPrints = BillPrintService(
    data: data,
    printing: printing,
    // Review P6 hardware/LAN (C2): the paired terminal's own id, so a locked till (no bootstrap) still knows it is named
    deviceUuid: () =>
        session.bootstrap?.device?.uuid ?? auth.terminal?.device.uuid,
    now: now,
    signedIn: () => session.phase == PosSessionPhase.ready,
    // C6: a relayed request the hub printed at the start of an outage, announced by the server later
    printedOnLan: (String requestId) => lanBills?.printed(requestId) ?? false,
    header: () {
      final PosBootstrap? b =
          session.bootstrap ?? data.offline?.storedBootstrap();
      return ReceiptHeader(
        storeName: b?.store.name ?? session.storeName ?? 'Kassa',
        address: b?.branch.address,
        phone: b?.branch.phone,
      );
    },
    adjust: CashierPrefs.instance.apply,
  );

  /// POS ma'lumot qatlami: sessiya, tasdiq, repozitoriylar.
  final PosData data;
  final ShellSettingsController settings;
  final CashierPrinting printing;

  /// P6 uskunalar: printerni qidirish, skanerlar, ikkinchi ekran (Sunmi D2).
  final CashierHardware hardware;
  final RealtimeFactory? realtimeFactory;
  final DateTime Function()? clock;

  /// Real-time jonli bo'lmaganda ro'yxatlar shu oraliqda yangilanadi ([PosRefreshScheduler]).
  final Duration pollInterval;
  final Duration livePollInterval;

  /// P6-41: kutilayotgan QR holati shu oraliqda so'raladi (real-time bo'lmasa ham to'lov o'zi ko'rinadi).
  final Duration qrPollInterval;

  AuthSession get auth => client.auth;
  PosSession get session => data.session;

  /// P3-31: ekran qulfi internetsiz rejimda to'xtaydi — PIN faqat serverda tekshiriladi, qulflangan kassaga uzilish
  /// paytida hech kim qayta kira olmaydi (docs/POS_SPEC.md 5.4).
  Duration? idleTimeout() =>
      data.offline?.active ?? false ? null : session.idleTimeout();

  /// Ekran qulfi muddati o'zgarishi mumkin bo'lgan paytlar (bootstrap, internetsiz rejimga kirish va chiqish).
  Stream<Object?> get idleChanges {
    final PosOffline? offline = data.offline;
    if (offline == null) return session.changes;
    late final StreamController<Object?> out;
    final List<StreamSubscription<Object?>> subs =
        <StreamSubscription<Object?>>[];
    bool? active;
    out = StreamController<Object?>.broadcast(
      onListen: () {
        subs
          ..add(session.changes.listen(out.add))
          ..add(
            offline.changes.listen((OfflineStatus s) {
              if (s.active != active) {
                active = s.active;
                out.add(s);
              }
            }),
          );
      },
      onCancel: () async {
        for (final StreamSubscription<Object?> s in subs) {
          await s.cancel();
        }
        subs.clear();
      },
    );
    return out.stream;
  }

  DateTime now() => (clock ?? DateTime.now)();
}

/// Ilova sozlamalaridan real-time ([AppConfig.reverbKey] bo'sh bo'lsa `null`).
PosRealtime? defaultRealtime(RizqimClient client, PairedTerminal terminal) =>
    PosRealtime.create(
      client: client,
      terminal: terminal,
      apiBaseUrl: AppConfig.apiBaseUrl,
      appKey: AppConfig.reverbKey,
      reverbUrl: AppConfig.reverbUrl,
      origin: AppConfig.reverbOrigin,
      clientName: 'rizqim-cashier',
    );
