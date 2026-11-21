import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';

import 'support/fake_kds.dart';
import 'support/fake_kitchen_state.dart';
import 'support/helpers.dart';

/// M5 S5: the corner mark of the kitchen screen and the pairing refusal for an outdated version (426).
const Key _mark = ValueKey<String>('pos-update-mark');

PosAppRelease _release(String state) => PosAppRelease(
  state: PosReleaseState.of(state),
  current: '0.2.0+2',
  latest: '0.3.0+4',
  minimum: state == 'update_required' ? '0.3.0+4' : null,
  url: 'https://pos.rizqim.uz/files/kds/android/rizqim-kds-0.3.0.apk',
);

KdsHarness _harness(String? state) {
  final KdsHarness h = KdsHarness(realtime: false);
  h.services.releases = PosReleaseRepository(
    fetch: () async => state == null ? null : _release(state),
  );
  return h;
}

void main() {
  testWidgets('no notice: no mark on the PIN screen', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = _harness(null);
    await h.services.releases!.refresh();
    await pumpPaired(tester, h);
    expect(find.text('Kim ishlaydi?'), findsOneWidget);
    expect(find.byKey(_mark), findsNothing);
  });

  for (final String state in <String>['update_available', 'update_required']) {
    testWidgets('$state: the mark shows on the PIN screen and on the board', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = _harness(state);
      await h.services.releases!.refresh();
      await pumpBoard(tester, h);
      expect(find.byKey(_mark), findsOneWidget);
      // Only a mark: the board still works under it
      expect(find.byType(Scaffold), findsWidgets);
    });
  }

  testWidgets('the mark appears when the notice arrives later', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = _harness(null);
    PosAppRelease? answer;
    h.services.releases = PosReleaseRepository(fetch: () async => answer);
    await pumpPaired(tester, h);
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
    final KdsHarness h = _harness(null);
    h.services.linkOpener = (Uri url) async {
      opened.add(url);
      return true;
    };
    h.backend.respond = (RequestOptions o) {
      if (o.method == 'POST' && o.uri.path.endsWith('/v1/devices/pair')) {
        return const FakeResponse(426, <String, Object?>{
          'success': false,
          'message': 'Ilova eskirgan: yangi versiyani yuklab oling.',
          'data': <String, Object?>{
            'reason': 'app_outdated',
            'current': '0.2.0+2',
            'minimum': '0.3.0+4',
            'url': 'https://pos.rizqim.uz/files/kds/android/new.apk',
          },
        });
      }
      return null;
    };
    await pumpKds(tester, h);
    await tester.enterText(find.byType(TextField), 'ABCD-EFGH');
    await tester.pump();
    await tester.tap(buttonLabeled('Ulash'));
    await tester.pumpAndSettle();

    expect(
      find.text('Ilova eskirgan: yangi versiyani yuklab oling.'),
      findsOneWidget,
    );
    await tester.tap(buttonLabeled('Yuklab olish'));
    await tester.pump();
    expect(opened.single.toString(), endsWith('/kds/android/new.apk'));
    expect(find.text('Terminalni ulash'), findsOneWidget);
  });
}
