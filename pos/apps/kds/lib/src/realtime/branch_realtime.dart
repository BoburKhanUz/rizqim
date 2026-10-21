import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// Filialning POS kanali (`private-pos-branch.{branchId}`, docs/API.md 8-bo'lim) — terminal tokeni bilan imzolanadi,
/// shuning uchun oshpaz PIN bilan kirmagan bo'lsa ham ishlaydi. Terminal ulanganda boshlanadi, uzilganda to'xtaydi.
///
/// "Jonli" ([isLive]) — ulangan **va** kanal obunasi tasdiqlangan. Ulanish bor, lekin imzo rad etilgan (403, 429) yoki
/// server obunani tasdiqlamagan bo'lsa hodisalar kelmaydi: ekran polling holatini ko'rsatadi va har 15 soniyada so'raydi.
class BranchRealtime {
  BranchRealtime({required PusherClient client, required this.branchId})
    : _client = client {
    status.value = client.status;
    subscription.value =
        client.channelState(channel) ?? RealtimeSubscriptionState.pending;
    _statusSub = client.statuses.listen((RealtimeStatus s) => status.value = s);
    _channelSub = client.channelStates.listen((RealtimeChannelState s) {
      if (s.channel == channel) subscription.value = s.state;
    });
  }

  /// Ilova sozlamalaridan: kalit bo'lmasa yoki terminal filialga ulanmagan bo'lsa `null` (faqat polling).
  static BranchRealtime? create({
    required RizqimClient client,
    required PairedTerminal terminal,
    required String apiBaseUrl,
    required String appKey,
    String reverbUrl = '',
    String origin = '',
    RealtimeConnector connector = ioRealtimeConnector,
  }) {
    final int? branchId = terminal.branch?.id ?? terminal.device.branchId;
    if (appKey.isEmpty || branchId == null) return null;
    return BranchRealtime(
      branchId: branchId,
      client: PusherClient(
        uri: realtimeUri(
          apiBaseUrl: apiBaseUrl,
          appKey: appKey,
          override: reverbUrl,
          clientName: 'rizqim-kds',
        ),
        headers: <String, String>{
          'Origin': origin.isNotEmpty ? origin : originOf(apiBaseUrl),
        },
        connector: connector,
        authorize: client.channelAuth.terminal,
      ),
    );
  }

  static String channelFor(int branchId) => 'private-pos-branch.$branchId';

  final int branchId;
  final PusherClient _client;
  late final StreamSubscription<RealtimeStatus> _statusSub;
  late final StreamSubscription<RealtimeChannelState> _channelSub;

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

  /// [status] yoki [subscription] o'zgarsa.
  late final Listenable changes = Listenable.merge(<Listenable>[
    status,
    subscription,
  ]);

  Stream<RealtimeEvent> get events => _client.events;

  /// Ulangan va obuna tasdiqlangan: hodisalar keladi.
  bool get isLive =>
      status.value == RealtimeStatus.connected &&
      subscription.value == RealtimeSubscriptionState.subscribed;

  /// Ulangan, lekin kanalga obuna rad etildi (imzo 403/429 yoki server xatosi): fonda qayta so'ralmoqda.
  bool get subscriptionFailed =>
      status.value == RealtimeStatus.connected &&
      subscription.value == RealtimeSubscriptionState.failed;

  void start() {
    _client
      ..subscribe(channel)
      ..connect();
  }

  Future<void> dispose() async {
    // Ulanish va taymerlar darhol (sinxron) to'xtaydi
    final Future<void> closing = _client.dispose();
    await _statusSub.cancel();
    await _channelSub.cancel();
    await closing;
    status.dispose();
    subscription.dispose();
  }
}
