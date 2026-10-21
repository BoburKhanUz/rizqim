import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// Filialning POS kanali (`private-pos-branch.{branchId}`, docs/API.md 8-bo'lim) — terminal tokeni bilan imzolanadi,
/// shuning uchun oshpaz PIN bilan kirmagan bo'lsa ham ishlaydi. Terminal ulanganda boshlanadi, uzilganda to'xtaydi.
class BranchRealtime {
  BranchRealtime({required PusherClient client, required this.branchId})
    : _client = client {
    status.value = client.status;
    _statusSub = client.statuses.listen((RealtimeStatus s) => status.value = s);
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

  final ValueNotifier<RealtimeStatus> status = ValueNotifier<RealtimeStatus>(
    RealtimeStatus.idle,
  );

  Stream<RealtimeEvent> get events => _client.events;

  bool get isLive => status.value == RealtimeStatus.connected;

  void start() {
    _client
      ..subscribe(channelFor(branchId))
      ..connect();
  }

  Future<void> dispose() async {
    // Ulanish va taymerlar darhol (sinxron) to'xtaydi
    final Future<void> closing = _client.dispose();
    await _statusSub.cancel();
    await closing;
    status.dispose();
  }
}
