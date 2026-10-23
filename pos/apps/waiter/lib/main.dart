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
  );
  // Terminal tokeni qurilmadan qaytariladi: qayta ishga tushganda qayta ulash shart emas
  await client.auth.restore();
  runApp(
    WaiterApp(
      themeMode: AppConfig.themeMode,
      services: WaiterServices(
        client: client,
        realtimeFactory: defaultRealtime,
      ),
    ),
  );
}
