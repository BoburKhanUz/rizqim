import 'package:flutter/material.dart';
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
  runApp(
    CashierApp(
      services: CashierServices(
        client: client,
        settings: settings,
        realtimeFactory: defaultRealtime,
      ),
    ),
  );
}
