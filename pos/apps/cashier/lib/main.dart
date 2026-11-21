import 'dart:io';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:pos_sync/pos_sync.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'src/app.dart';
import 'src/app_config.dart';
import 'src/hardware/customer_screen_app.dart';
import 'src/services.dart';
import 'src/storage/secure_storage_store.dart';
import 'src/storage/shell_settings.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final RizqimClient client = RizqimClient(
    baseUrl: AppConfig.apiBaseUrl,
    store: SecureStorageStore(),
    app: 'cashier',
  );
  final ShellSettingsController settings = ShellSettingsController(
    PrefsShellSettingsStore(),
  );
  // Terminal tokeni qurilmadan qaytariladi: qayta ishga tushganda qayta ulash shart emas
  await Future.wait(<Future<void>>[client.auth.restore(), settings.load()]);
  // P3-31: internetsiz rejim navbati va nusxalari ilovaning o'z papkasida (elektr o'chsa ham qoladi)
  final Directory support = await getApplicationSupportDirectory();
  final FileSyncStorage journal = FileSyncStorage(
    Directory('${support.path}/pos_sync'),
  );
  // M5 S4: a second copy of the journal outside the app's folder, restored from on start (MACs verified)
  final SyncStore offline = await SyncStore.open(
    await _mirrored(journal, client.auth.terminal),
  );
  runApp(
    CashierApp(
      services: CashierServices(
        client: client,
        settings: settings,
        realtimeFactory: defaultRealtime,
        offlineStore: offline,
        // P6 local bridge: the hub's keys (never in the plain offline copy)
        lanStore: SecureStorageStore(),
      ),
    ),
  );
}

/// M5 S4 (docs/scale/M5_DESIGN.md 5, layer 2): the journal with its mirror copy in `Documents\RIZQIM\<device uuid>\`
/// (Windows) or `<external files>/RIZQIM/<device uuid>/` (Android). Before the store opens, operations the journal
/// lost are put back from the copy when their MAC verifies under the till's key. Not gated on anything in the
/// journal's folder (review 1: the bootstrap copy that said `offline.export` is lost with that folder): a verified
/// line is the till's own and the server takes an `op_id` once; what cannot be verified stays in the copy's folder
/// and goes into the export file. An unpaired till, another platform or any failure here: the journal alone (a
/// backup never stops the till from starting; the copy is waited for a bounded time).
Future<SyncStorage> _mirrored(
  FileSyncStorage journal,
  PairedTerminal? terminal,
) async {
  if (terminal == null) return journal;
  try {
    final Directory? base = Platform.isWindows
        ? await getApplicationDocumentsDirectory()
        : Platform.isAndroid
        ? await getExternalStorageDirectory()
        : null;
    if (base == null) return journal;
    final Directory dir = mirrorDirectoryIn(base, terminal.device.uuid);
    final MirroredSyncStorage mirrored = MirroredSyncStorage(
      journal,
      FileSyncStorage(dir),
      directory: dir,
    );
    await mirrored.restore(keys: <SyncKey>[?_syncKey(terminal)]);
    return mirrored;
  } on Object {
    return journal;
  }
}

/// The till's sync key, in memory only (never logged or written outside its secure storage).
SyncKey? _syncKey(PairedTerminal terminal) {
  final TerminalSyncKey? saved = terminal.syncKey;
  if (saved == null) return null;
  try {
    return SyncKey.fromBase64(
      saved.key,
      kid: saved.kid,
      deviceUuid: terminal.device.uuid,
    );
  } on Object {
    return null;
  }
}

/// P6 uskunalar: kassaning ikkinchi ekrani (Sunmi D2 turi). `pos_hardware` plagini uni ikkinchi ekranda alohida
/// Flutter dvigatelida ishga tushiradi; chek kassadan qurilma ichida keladi (server kerak emas).
@pragma('vm:entry-point')
void customerScreenMain() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(CustomerScreenApp(receiver: CustomerScreenReceiver()));
}
