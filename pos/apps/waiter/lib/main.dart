import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'src/app.dart';
import 'src/app_config.dart';
import 'src/services.dart';
import 'src/storage/secure_storage_store.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final RizqimClient client = RizqimClient(
    baseUrl: AppConfig.apiBaseUrl,
    store: SecureStorageStore(),
    app: 'waiter',
    appVersion: AppConfig.fullVersion,
  );
  // Terminal tokeni qurilmadan qaytariladi: qayta ishga tushganda qayta ulash shart emas
  await client.auth.restore();
  runApp(
    WaiterApp(
      themeMode: AppConfig.themeMode,
      services: WaiterServices(
        client: client,
        realtimeFactory: defaultRealtime,
        lanStore: SecureStorageStore(),
        // M5 S5: the day the "new version" banner was dismissed (a small value, kept like the LAN keys)
        prefsStore: SecureStorageStore(),
      ),
    ),
  );
}
