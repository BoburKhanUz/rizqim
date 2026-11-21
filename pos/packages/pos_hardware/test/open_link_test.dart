import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_hardware/pos_hardware.dart';

/// M5 S5: openLink opens https links only, over the Android channel or `explorer` on Windows.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final TestDefaultBinaryMessenger messenger =
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  tearDown(() => messenger.setMockMethodCallHandler(linkChannel, null));

  final Uri good = Uri.parse('https://pos.rizqim.uz/files/cashier/a.apk');

  group('scheme validation', () {
    test('only https with a host and no login part', () {
      expect(isOpenableLink(good), isTrue);
      for (final String bad in <String>[
        'http://pos.rizqim.uz/a.apk',
        'file:///C:/Windows/System32/calc.exe',
        'javascript:alert(1)',
        'intent://x#Intent;end',
        'ftp://pos.rizqim.uz/a',
        'https:///nohost',
        'https://user:pw@pos.rizqim.uz/a',
        'pos.rizqim.uz/a',
      ]) {
        expect(isOpenableLink(Uri.parse(bad)), isFalse, reason: bad);
      }
    });

    test('a refused link never reaches the system', () async {
      final List<String> calls = <String>[];
      messenger.setMockMethodCallHandler(linkChannel, (MethodCall c) async {
        calls.add(c.method);
        return true;
      });
      expect(
        await openLink(
          Uri.parse('http://x.uz/a'),
          android: true,
          windows: false,
        ),
        isFalse,
      );
      expect(
        await openLink(
          Uri.parse('file:///x'),
          android: false,
          windows: true,
          start: (String e, List<String> a) async => calls.add(e),
        ),
        isFalse,
      );
      expect(calls, isEmpty);
    });
  });

  group('Android', () {
    test('goes over the channel with the url', () async {
      MethodCall? seen;
      messenger.setMockMethodCallHandler(linkChannel, (MethodCall c) async {
        seen = c;
        return true;
      });
      expect(await openLink(good, android: true, windows: false), isTrue);
      expect(seen!.method, 'open');
      expect(
        (seen!.arguments as Map<Object?, Object?>)['url'],
        good.toString(),
      );
    });

    test(
      'a refusal, a missing plugin or an error is false, not thrown',
      () async {
        expect(await openLink(good, android: true, windows: false), isFalse);
        messenger.setMockMethodCallHandler(
          linkChannel,
          (MethodCall c) async => false,
        );
        expect(await openLink(good, android: true, windows: false), isFalse);
        messenger.setMockMethodCallHandler(
          linkChannel,
          (MethodCall c) async => throw PlatformException(code: 'x'),
        );
        expect(await openLink(good, android: true, windows: false), isFalse);
      },
    );
  });

  group('Windows', () {
    test('runs explorer with the url as one argument', () async {
      String? exe;
      List<String>? args;
      final bool ok = await openLink(
        good,
        android: false,
        windows: true,
        start: (String e, List<String> a) async {
          exe = e;
          args = a;
        },
      );
      expect(ok, isTrue);
      expect(exe, 'explorer');
      expect(args, <String>[good.toString()]);
    });

    test('a start that fails is false', () async {
      expect(
        await openLink(
          good,
          android: false,
          windows: true,
          start: (String e, List<String> a) async => throw Exception('no'),
        ),
        isFalse,
      );
    });
  });

  test('elsewhere nothing is opened', () async {
    expect(await openLink(good, android: false, windows: false), isFalse);
  });
}
