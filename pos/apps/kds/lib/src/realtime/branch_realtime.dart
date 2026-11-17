import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// Filialning POS kanali (`private-pos-branch.{branchId}`, docs/API.md 8-bo'lim) — terminal tokeni bilan imzolanadi,
/// shuning uchun oshpaz PIN bilan kirmagan bo'lsa ham ishlaydi. Terminal ulanganda boshlanadi, uzilganda to'xtaydi.
///
/// "Jonli" ([isLive]) — ulangan **va** kanal obunasi tasdiqlangan, M2: va server real-time'ni jonli deydi
/// (`GET /v1/realtime`), kanal `silence_seconds` davomida jim emas ([degraded]). Ulanish bor, lekin imzo rad etilgan
/// (403, 429) yoki server obunani tasdiqlamagan bo'lsa hodisalar kelmaydi: ekran polling holatini ko'rsatadi.
///
/// Kanalning o'zi [LiveChannel] da (POS ilovalaridagi `PosRealtime` bilan bir xil yadro): heartbeat [heartbeats] da,
/// [events] da emas; ilovaga kalit yozilmagan bo'lsa (M2) kalit, xost va port serverdan olinadi.
class BranchRealtime {
  BranchRealtime({
    required PusherClient client,
    required this.branchId,
    Future<RealtimeConfig?> Function()? loadConfig,
    Uri? Function(RealtimeConfig? config)? uriFor,
    bool waitForConfig = false,
  }) : _link = LiveChannel(
         client: client,
         channel: channelFor(branchId),
         loadConfig: loadConfig,
         uriFor: uriFor,
         waitForConfig: waitForConfig,
       ) {
    _sync();
    _changeSub = _link.changes.listen((_) => _sync());
  }

  /// Ilova sozlamalaridan; terminal filialga ulanmagan bo'lsa `null` (faqat polling). [appKey] bo'sh bo'lsa (M2) kalit
  /// serverning `GET /v1/realtime` javobidan olinadi.
  static BranchRealtime? create({
    required RizqimClient client,
    required PairedTerminal terminal,
    required String apiBaseUrl,
    required String appKey,
    String reverbUrl = '',
    String origin = '',
    RealtimeConnector connector = ioRealtimeConnector,
    Backoff backoff = Backoff.reconnect,
    Future<RealtimeConfig?> Function()? loadConfig,
  }) {
    final int? branchId = terminal.branch?.id ?? terminal.device.branchId;
    if (branchId == null) return null;
    Uri? uriFor(RealtimeConfig? config) => RealtimeConfig.socketUri(
      apiBaseUrl: apiBaseUrl,
      appKey: appKey,
      config: config,
      override: reverbUrl,
      clientName: 'rizqim-kds',
    );
    return BranchRealtime(
      branchId: branchId,
      loadConfig: loadConfig ?? () => RealtimeConfig.fetch(client.dio),
      uriFor: uriFor,
      waitForConfig: appKey.isEmpty,
      client: PusherClient(
        uri:
            uriFor(null) ??
            realtimeUri(
              apiBaseUrl: apiBaseUrl,
              appKey: '',
              override: reverbUrl,
              clientName: 'rizqim-kds',
            ),
        headers: <String, String>{
          'Origin': origin.isNotEmpty ? origin : originOf(apiBaseUrl),
        },
        connector: connector,
        backoff: backoff,
        authorize: client.channelAuth.terminal,
      ),
    );
  }

  static String channelFor(int branchId) => 'private-pos-branch.$branchId';

  final int branchId;
  final LiveChannel _link;
  late final StreamSubscription<void> _changeSub;

  String get channel => channelFor(branchId);

  /// WebSocket ulanishi.
  final ValueNotifier<RealtimeStatus> status = ValueNotifier<RealtimeStatus>(
    RealtimeStatus.idle,
  );

  /// Filial kanaliga obuna.
  final ValueNotifier<RealtimeSubscriptionState> subscription =
      ValueNotifier<RealtimeSubscriptionState>(
        RealtimeSubscriptionState.pending,
      );

  /// M2: obuna bor, lekin server `live: false` dedi yoki kanal jim: tez polling.
  final ValueNotifier<bool> degraded = ValueNotifier<bool>(false);

  /// [status], [subscription] yoki [degraded] o'zgarsa.
  late final Listenable changes = Listenable.merge(<Listenable>[
    status,
    subscription,
    degraded,
  ]);

  /// Kanal hodisalari (heartbeat'siz).
  Stream<RealtimeEvent> get events => _link.events;

  /// M2: `realtime.heartbeat`.
  Stream<RealtimeEvent> get heartbeats => _link.heartbeats;

  /// Obuna (qayta) tasdiqlandi: uzilish paytidagi hodisalar yo'qolgan, ro'yxat to'liq qayta o'qiladi.
  Stream<void> get resubscribed => _link.resubscribed;

  /// Ulangan, obuna tasdiqlangan va [degraded] emas: hodisalarga ishonsa bo'ladi.
  bool get isLive => _link.isLive;

  /// Ulangan va obuna tasdiqlangan (jim yoki server `live: false` bo'lsa ham).
  bool get subscribed => _link.subscribed;

  /// Ulangan, lekin kanalga obuna rad etildi (imzo 403/429 yoki server xatosi): fonda qayta so'ralmoqda.
  bool get subscriptionFailed =>
      status.value == RealtimeStatus.connected &&
      subscription.value == RealtimeSubscriptionState.failed;

  /// Kanal (sozlama, jimlik; testlar).
  LiveChannel get link => _link;

  void start() => _link.start();

  void _sync() {
    status.value = _link.status;
    subscription.value = _link.subscription;
    degraded.value = _link.degraded;
  }

  Future<void> dispose() async {
    // Ulanish va taymerlar darhol (sinxron) to'xtaydi
    final Future<void> closing = _link.dispose();
    await _changeSub.cancel();
    await closing;
    status.dispose();
    subscription.dispose();
    degraded.dispose();
  }
}
