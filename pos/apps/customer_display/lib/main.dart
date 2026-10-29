import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'src/app.dart';
import 'src/app_config.dart';
import 'src/secure_storage_store.dart';
import 'src/settings/display_settings.dart';
import 'src/sound/ready_sound.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final RizqimClient client = RizqimClient(
    baseUrl: AppConfig.apiBaseUrl,
    store: SecureStorageStore(),
  );
  final DisplaySettingsController settings = DisplaySettingsController(
    PrefsSettingsStore(),
  );
  // Terminal tokeni qurilmadan qaytariladi: qayta ishga tushganda qayta ulash shart emas
  await Future.wait(<Future<void>>[client.auth.restore(), settings.load()]);
  runApp(
    DisplayApp(
      services: DisplayServices(
        client: client,
        settings: settings,
        sound: AssetReadySound(),
        realtimeFactory: defaultRealtime,
      ),
    ),
  );
}
