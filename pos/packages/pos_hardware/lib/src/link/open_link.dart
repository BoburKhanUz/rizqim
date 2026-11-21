import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// Starts a program without waiting for it (`explorer <url>` on Windows). Replaceable in tests.
typedef LinkProcessStarter = Future<void> Function(
  String executable,
  List<String> arguments,
);

/// M5 S5: opens a download link in the system browser (the update notice's "Yangilash"). Only an `https` link with a
/// host is opened; anything else (`file:`, `http:`, `intent:`, `javascript:`, a link with a login part) is refused
/// without touching the system.
///
/// * Android: the plugin's `uz.rizqim.pos_hardware/link` channel, method `open` (`url` string) -> `bool`
///   (`LinkOpener.kt`: `ACTION_VIEW` in a new task). The browser downloads the APK and offers to install it over
///   the existing app (data, pairing and journal are kept).
/// * Windows: `explorer <url>` through `dart:io` (the URL is one argument, never passed through a shell).
/// * Elsewhere (Linux, tests): `false`.
///
/// Returns `true` when the system accepted the request, `false` otherwise. Never throws.
Future<bool> openLink(
  Uri url, {
  MethodChannel? channel,
  bool? android,
  bool? windows,
  LinkProcessStarter? start,
}) async {
  if (!isOpenableLink(url)) return false;
  final bool onAndroid =
      android ?? defaultTargetPlatform == TargetPlatform.android;
  final bool onWindows = windows ?? (!kIsWeb && Platform.isWindows);
  try {
    if (onAndroid) {
      final bool? ok = await (channel ?? linkChannel).invokeMethod<bool>(
        'open',
        <String, Object?>{'url': url.toString()},
      );
      return ok ?? false;
    }
    if (onWindows) {
      // explorer.exe answers exit code 1 even when it opened the page: only a start that works counts
      await (start ?? _start)('explorer', <String>[url.toString()]);
      return true;
    }
  } on MissingPluginException {
    return false;
  } on PlatformException {
    return false;
  } on Object {
    return false;
  }
  return false;
}

/// The channel of the Android side (`LinkOpener.kt`).
const MethodChannel linkChannel = MethodChannel('uz.rizqim.pos_hardware/link');

/// `https` with a host and no login part; the only links [openLink] opens.
bool isOpenableLink(Uri url) =>
    url.scheme == 'https' && url.host.isNotEmpty && url.userInfo.isEmpty;

Future<void> _start(String executable, List<String> arguments) async {
  await Process.start(executable, arguments, mode: ProcessStartMode.detached);
}
