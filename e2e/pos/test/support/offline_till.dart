import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'stack.dart';

/// The owner's API (the partner web): the seeded owner's token.
Dio ownerDio(StackConfig config) => Dio(
  BaseOptions(
    baseUrl: config.apiUrl,
    headers: <String, Object?>{
      'Authorization': 'Bearer ${config.seed.ownerToken}',
      'Accept': 'application/json',
    },
  ),
);

Map<String, Object?> asMap(Object? value) =>
    (value! as Map<Object?, Object?>).cast<String, Object?>();

List<Map<String, Object?>> asList(Object? value) => <Map<String, Object?>>[
  for (final Object? e in (value as List<Object?>?) ?? const <Object?>[])
    asMap(e),
];

/// The owner's call; answers `data`.
Future<Map<String, Object?>> ownerCall(
  Dio owner,
  String method,
  String path, [
  Object? body,
]) async {
  final Response<Object?> r = await owner.request<Object?>(
    path,
    data: body,
    options: Options(method: method),
  );
  return asMap(asMap(r.data)['data']);
}

/// Cuts the till off the server: every request fails as if the cable were out.
class OfflineCable {
  bool cut = false;

  Interceptor get interceptor => InterceptorsWrapper(
    onRequest: (RequestOptions options, RequestInterceptorHandler handler) {
      if (!cut) return handler.next(options);
      handler.reject(
        DioException(
          requestOptions: options,
          type: DioExceptionType.connectionError,
          error: const SocketException('e2e: the till\'s cable is out'),
        ),
      );
    },
  );
}

/// What went over the wire to `POST /v1/pos/sync`: the request bodies and the answers.
class SyncWire {
  final List<Map<String, Object?>> requests = <Map<String, Object?>>[];
  final List<Map<String, Object?>> answers = <Map<String, Object?>>[];

  Interceptor get interceptor => InterceptorsWrapper(
    onRequest: (RequestOptions options, RequestInterceptorHandler handler) {
      if (options.method == 'POST' && options.path.endsWith('/pos/sync')) {
        requests.add(asMap(jsonDecode(jsonEncode(options.data))));
      }
      handler.next(options);
    },
    onResponse: (Response<Object?> response, ResponseInterceptorHandler h) {
      if (response.requestOptions.method == 'POST' &&
          response.requestOptions.path.endsWith('/pos/sync') &&
          response.data is Map) {
        answers.add(asMap(response.data));
      }
      h.next(response);
    },
  );

  /// Every operation sent, as the server read it: a signed line is read from its `signed` text (the only text the
  /// server applies), an unsigned one is the operation itself. `signed: true` when the line carried a signature.
  List<({Map<String, Object?> op, bool signed})> get operations =>
      <({Map<String, Object?> op, bool signed})>[
        for (final Map<String, Object?> r in requests)
          for (final Map<String, Object?> line in asList(r['operations']))
            if (line['signed'] is String)
              (op: asMap(jsonDecode(line['signed']! as String)), signed: true)
            else
              (op: line, signed: false),
      ];

  /// The server's answer for an operation (the last one given), by `op_id`.
  Map<String, Object?>? resultOf(String opId) {
    Map<String, Object?>? found;
    for (final Map<String, Object?> a in answers) {
      for (final Map<String, Object?> r in asList(
        asMap(a['data'])['results'],
      )) {
        if (r['op_id'] == opId) found = r;
      }
    }
    return found;
  }
}

/// A till of its own for one scenario: paired with a code the owner makes (so the seeded tills of the other files are
/// never revoked or edited), with its own journal on a memory disk, its cable and the sync wire.
class OfflineTill {
  OfflineTill._(
    this.terminal,
    this.disk,
    this.store,
    this.cable,
    this.wire,
    this.owner,
    this.config,
    this.clock,
  );

  Terminal terminal;
  final MemorySyncStorage disk;
  SyncStore store;
  final OfflineCable cable;
  final SyncWire wire;
  final Dio owner;
  final StackConfig config;
  final DateTime Function() clock;

  PosData get pos => terminal.pos;
  RizqimClient get client => terminal.client;
  String get deviceUuid => client.auth.terminal!.device.uuid;

  /// A pairing code from the owner. The route is limited to 5 a minute: a 429 waits for the time it names.
  static Future<String> pairingCode(
    StackConfig config,
    Dio owner,
    String name,
  ) async {
    for (var attempt = 0; ; attempt++) {
      try {
        final Map<String, Object?> made = await ownerCall(
          owner,
          'POST',
          '/v1/owner/devices/pairing-codes',
          <String, Object?>{
            'type': 'pos',
            'branch_id': config.seed.branchId,
            'name': name,
          },
        );
        return made['code']! as String;
      } on DioException catch (e) {
        if (e.response?.statusCode != 429 || attempt >= 3) rethrow;
        final int wait =
            int.tryParse(e.response?.headers.value('retry-after') ?? '') ?? 60;
        await Future<void>.delayed(Duration(seconds: wait + 1));
      }
    }
  }

  static Future<OfflineTill> pair(
    StackConfig config,
    Dio owner,
    String name, {
    DateTime Function()? clock,
  }) async {
    final DateTime Function() now = clock ?? DateTime.now;
    final RizqimClient client = RizqimClient(
      baseUrl: config.apiUrl,
      store: MemoryKeyValueStore(),
    );
    await client.auth.pairTerminal(
      await pairingCode(config, owner, name),
      name: name,
      platform: 'e2e',
      appVersion: '0.1.0',
    );
    final MemorySyncStorage disk = MemorySyncStorage();
    final SyncStore store = await SyncStore.open(disk);
    final OfflineCable cable = OfflineCable();
    final SyncWire wire = SyncWire();
    client.dio.interceptors
      ..add(cable.interceptor)
      ..add(wire.interceptor);
    final PosData pos = PosData(client, offlineStore: store);
    return OfflineTill._(
      Terminal.adopt(name, config, client, pos),
      disk,
      store,
      cable,
      wire,
      owner,
      config,
      now,
    );
  }

  /// The staff member types the PIN; the bootstrap, the menus, the floor and the open orders are loaded (and kept for
  /// the outage).
  Future<void> signIn(String staffKey) async {
    await terminal.signIn(staffKey);
    final PosBootstrap boot = terminal.bootstrap;
    await pos.catalog.load(PosChannel.takeaway, menuVersion: boot.menuVersion);
    await pos.catalog.load(PosChannel.hall, menuVersion: boot.menuVersion);
    await pos.floor.load();
    await pos.orders.openOrders();
  }

  /// Orders the scenario opened (their server or local uuids), so that the test can cancel what it leaves open: the
  /// branch is shared with the other files, which count its open orders.
  final List<String> opened = <String>[];

  /// The shift the scenario opened (the cleanup pays with it).
  String? shiftUuid;

  /// A takeaway order that is not sent to the kitchen (the Thursday evening's cook bumps every ticket of the branch).
  Future<PosOrder> openTakeaway(List<PosLineInput> items) async {
    final PosOrder order = await pos.orders.open(
      channel: PosChannel.takeaway,
      items: items,
      send: false,
    );
    opened.add(order.uuid);
    return order;
  }

  /// Cancels every order the scenario opened and left open (a manager's PIN approves), as the last thing it does.
  Future<void> cancelLeftovers(Staff manager) async {
    for (final String id in opened) {
      try {
        final PosOrder order = await pos.orders.get(store.resolve(id));
        if (order.status == 'canceled' || order.status == 'delivered') continue;
        try {
          await runWithApproval<PosOrder>(
            (String? token) => pos.orders.cancel(
              order.uuid,
              version: order.version,
              reason: 'Sinov tugadi',
              approvalToken: token,
            ),
            approve: (String permission, PosException cause) =>
                pos.approvals.request(
                  permission: permission,
                  userId: manager.id,
                  pin: manager.pin!,
                ),
          );
        } on PosConflict {
          // Money is on it (an edited payment left it underpaid, or a payment found no bill): a bill is made if there
          // is none and the rest is paid in cash, which closes the order
          List<PosBill> bills = (await pos.billing.bills(order.uuid)).bills;
          if (bills.isEmpty) {
            bills = (await pos.billing.createBills(
              order.uuid,
              version: order.version,
            )).bills;
          }
          for (final PosBill b in bills) {
            if (b.isPaid || !b.dueAmount.isPositive) continue;
            await pos.billing.pay(
              b.uuid,
              tenders: <PosTenderInput>[PosTenderInput.cash(b.dueAmount)],
              shiftUuid: shiftUuid,
            );
          }
        }
      } on Object catch (e) {
        stderr.writeln(
          '[pos-e2e] offline scenarios: cleanup of $id (${terminal.label}): $e',
        );
      }
    }
  }

  /// The cable is cut and the till finds out.
  void goOffline() {
    cable.cut = true;
    pos.connectivity.reportFailure(
      const SocketException('e2e: the cable is out'),
    );
  }

  /// The cable is back; the journal goes to the server.
  Future<SyncDrainResult> comeBack() async {
    cable.cut = false;
    pos.connectivity.reportReachable();
    return pos.offline!.syncNow();
  }

  /// The app is closed (the journal stays on the disk, the pairing on the device).
  Future<void> stopApp() async {
    await pos.dispose();
    // (anything the closing app still writes to its journal lands before the test touches the disk)
    await Future<void>.delayed(const Duration(milliseconds: 300));
  }

  /// The app is started again on the same disk, with the same pairing (the cashier app's `main`).
  Future<void> startApp() async {
    store = await SyncStore.open(disk);
    final PosData fresh = PosData(client, offlineStore: store);
    terminal = Terminal.adopt(terminal.label, config, client, fresh);
  }

  Future<void> restart() async {
    await stopApp();
    await startApp();
  }

  Future<void> close() => terminal.close();
}
