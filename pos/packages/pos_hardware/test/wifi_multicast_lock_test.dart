import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_hardware/pos_hardware.dart';

/// P6 lokal ko'prik: the Wi-Fi multicast lock over its channel (a fake plugin), and a no-op elsewhere.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final TestDefaultBinaryMessenger messenger =
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  const MethodChannel channel = WifiMulticastLock.defaultChannel;

  tearDown(() => messenger.setMockMethodCallHandler(channel, null));

  test('Android: acquire and release go to the plugin', () async {
    final List<String> calls = <String>[];
    messenger.setMockMethodCallHandler(channel, (MethodCall call) async {
      calls.add(call.method);
      return call.method == 'acquire' ? true : null;
    });
    final WifiMulticastLock lock = WifiMulticastLock(android: true);
    expect(await lock.acquire(), isTrue);
    await lock.release();
    expect(calls, <String>['acquire', 'release']);
  });

  test('no plugin or another platform: nothing held, nothing thrown', () async {
    expect(await WifiMulticastLock(android: true).acquire(), isFalse);
    await WifiMulticastLock(android: true).release();
    final List<String> calls = <String>[];
    messenger.setMockMethodCallHandler(channel, (MethodCall call) async {
      calls.add(call.method);
      return true;
    });
    expect(await WifiMulticastLock(android: false).acquire(), isFalse);
    expect(calls, isEmpty);
  });
}
