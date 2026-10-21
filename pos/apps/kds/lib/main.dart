import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'src/app.dart';
import 'src/app_config.dart';
import 'src/secure_storage_store.dart';
import 'src/settings/kds_settings.dart';
import 'src/sound/ticket_sound.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final RizqimClient client = RizqimClient(
    baseUrl: AppConfig.apiBaseUrl,
    store: SecureStorageStore(),
  );
  final KdsSettingsController settings = KdsSettingsController(
    PrefsSettingsStore(),
  );
  // Terminal tokeni qurilmadan qaytariladi: qayta ishga tushganda qayta ulash shart emas
  await Future.wait(<Future<void>>[client.auth.restore(), settings.load()]);
  runApp(
    KdsApp(
      services: KdsServices(
        client: client,
        settings: settings,
        sound: AssetTicketSound(),
        realtimeFactory: defaultRealtime,
      ),
    ),
  );
}
