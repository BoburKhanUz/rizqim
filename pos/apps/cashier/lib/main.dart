import 'dart:io';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:pos_sync/pos_sync.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'src/app.dart';
import 'src/app_config.dart';
import 'src/services.dart';
import 'src/storage/secure_storage_store.dart';
import 'src/storage/shell_settings.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final RizqimClient client = RizqimClient(
    baseUrl: AppConfig.apiBaseUrl,
    store: SecureStorageStore(),
  );
  final ShellSettingsController settings = ShellSettingsController(
    PrefsShellSettingsStore(),
  );
  // Terminal tokeni qurilmadan qaytariladi: qayta ishga tushganda qayta ulash shart emas
  await Future.wait(<Future<void>>[client.auth.restore(), settings.load()]);
  // P3-31: internetsiz rejim navbati va nusxalari ilovaning o'z papkasida (elektr o'chsa ham qoladi)
  final Directory support = await getApplicationSupportDirectory();
  final SyncStore offline = await SyncStore.open(
    FileSyncStorage(Directory('${support.path}/pos_sync')),
  );
  runApp(
    CashierApp(
      services: CashierServices(
        client: client,
        settings: settings,
        realtimeFactory: defaultRealtime,
        offlineStore: offline,
      ),
    ),
  );
}
