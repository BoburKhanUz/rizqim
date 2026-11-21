import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';

import 'support/harness.dart';

/// M5 S5: the corner mark of the customer display and the pairing refusal for an outdated version (426).
const Key _mark = ValueKey<String>('pos-update-mark');

PosAppRelease _release(String state) => PosAppRelease(
  state: PosReleaseState.of(state),
  current: '0.1.0+1',
  latest: '0.2.0+3',
  minimum: state == 'update_required' ? '0.2.0+3' : null,
  url: 'https://pos.rizqim.uz/files/customer_display/android/new.apk',
);

DisplayHarness _harness(PosAppRelease? Function() answer) {
  final DisplayHarness h = DisplayHarness();
  h.backend.queueCalling = false;
  h.services.releases = PosReleaseRepository(fetch: () async => answer());
  return h;
}

void main() {
  testWidgets('no notice: no mark', (WidgetTester tester) async {
    final DisplayHarness h = _harness(() => null);
    await h.services.releases!.refresh();
    await h.pump(tester);
    expect(find.text('Xush kelibsiz!'), findsOneWidget);
    expect(find.byKey(_mark), findsNothing);
  });

  for (final String state in <String>['update_available', 'update_required']) {
    testWidgets('$state: the mark shows and the screen keeps working', (
      WidgetTester tester,
    ) async {
      final DisplayHarness h = _harness(() => _release(state));
      await h.services.releases!.refresh();
      await h.pump(tester);
      expect(find.byKey(_mark), findsOneWidget);
      expect(find.text('Xush kelibsiz!'), findsOneWidget);
    });
  }

  testWidgets('the mark appears when the notice arrives later', (
    WidgetTester tester,
  ) async {
    PosAppRelease? answer;
    final DisplayHarness h = _harness(() => answer);
    await h.pump(tester);
    expect(find.byKey(_mark), findsNothing);
    answer = _release('update_available');
    await h.services.releases!.refresh();
    await tester.pumpAndSettle();
    expect(find.byKey(_mark), findsOneWidget);
  });

  testWidgets('pairing 426: the message and a download link', (
    WidgetTester tester,
  ) async {
    final List<Uri> opened = <Uri>[];
    final DisplayHarness h = _harness(() => null);
    h.services.linkOpener = (Uri url) async {
      opened.add(url);
      return true;
    };
    final FakeResponse? Function(RequestOptions) inner = h.adapter.respond!;
    h.adapter.respond = (RequestOptions o) {
      if (o.method == 'POST' && o.uri.path.endsWith('/v1/devices/pair')) {
        return const FakeResponse(426, <String, Object?>{
          'success': false,
          'message': 'Ilova eskirgan: yangi versiyani yuklab oling.',
          'data': <String, Object?>{
            'reason': 'app_outdated',
            'current': '0.1.0+1',
            'minimum': '0.2.0+3',
            'url':
                'https://pos.rizqim.uz/files/customer_display/android/new.apk',
          },
        });
      }
      return inner(o);
    };
    await h.pump(tester, paired: false);
    await tester.enterText(find.byType(TextField), 'abcdefgh');
    await tester.pump();
    await tester.tap(find.text('Ulash'));
    await h.advance(tester, const Duration(seconds: 1));
    await tester.pumpAndSettle();

    expect(
      find.text('Ilova eskirgan: yangi versiyani yuklab oling.'),
      findsOneWidget,
    );
    await tester.tap(find.text('Yuklab olish'));
    await tester.pump();
    expect(
      opened.single.toString(),
      endsWith('/customer_display/android/new.apk'),
    );
    expect(find.text('Mijoz ekranini ulash'), findsOneWidget);
  });
}
