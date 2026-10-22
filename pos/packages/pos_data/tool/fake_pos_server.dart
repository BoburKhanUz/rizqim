// Soxta POS backend + Reverb: kassa va ofitsiant ilovalarini Linux'da backend'siz ishga tushirish va suratga olish uchun.
//
//   cd pos/packages/pos_data && dart run tool/fake_pos_server.dart --port 8788
//   cd pos/apps/cashier && flutter run -d linux --dart-define=RIZQIM_API_URL=http://127.0.0.1:8788/api \
//     --dart-define=RIZQIM_REVERB_KEY=pos-key --dart-define=RIZQIM_REVERB_URL=ws://127.0.0.1:8788
//
// Ulash kodi — istalgan 8 belgi (ABCD-EFGH). PIN: kassir Kamola — 2468, ofitsiant Botir — 1357, menejer Malika — 5926.
// `--empty`: smena yopiq, buyurtmasiz.
import 'dart:io';

import 'package:pos_data/testing.dart';

Future<void> main(List<String> args) async {
  final int i = args.indexOf('--port');
  final int port = i >= 0 && i + 1 < args.length
      ? int.parse(args[i + 1])
      : 8788;
  final FakePosServer server =
      await FakePosServer.start(port: port, demo: !args.contains('--empty'))
        ..verbose = true;
  stdout.writeln(
    'Soxta POS backend: ${server.apiBaseUrl} (Reverb: ${server.reverbUrl}/app/${FakePosState.realtimeKey})',
  );
}
