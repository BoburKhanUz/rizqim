import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_waiter/src/features/rooms/rooms_page.dart';

import 'support/harness.dart';

const Key _banner = ValueKey<String>('pos-update-banner');
const Key _notice = ValueKey<String>('update-required-notice');
const Key _continue = ValueKey<String>('update-notice-continue');

PosAppRelease _release(String state) => PosAppRelease(
  state: PosReleaseState.of(state),
  current: '0.1.0+1',
  latest: '0.2.0+5',
  minimum: state == 'update_required' ? '0.2.0+5' : null,
  url: 'https://pos.rizqim.uz/files/waiter/android/rizqim-waiter-0.2.0.apk',
);

/// A phone whose server answers [state] (`null`: no notice); the opened links are collected in [opened].
WaiterHarness _phone(String? state, List<Uri> opened) {
  final WaiterHarness h = WaiterHarness();
  h.services
    ..releases = PosReleaseRepository(
      fetch: () async => state == null ? null : _release(state),
    )
    ..linkOpener = (Uri url) async {
      opened.add(url);
      return true;
    };
  return h;
}

void main() {
  testWidgets('no notice from the server: no banner', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = _phone(null, <Uri>[]);
    await h.services.releases!.refresh();
    await pumpHome(tester, h);
    expect(find.byType(RoomsPage), findsOneWidget);
    expect(find.byKey(_banner), findsNothing);
    expect(find.byKey(_notice), findsNothing);
  });

  testWidgets(
    'update_available: dismissed stays hidden the same day, comes back the next day; Yangilash opens the link',
    (WidgetTester tester) async {
      final List<Uri> opened = <Uri>[];
      final WaiterHarness h = _phone('update_available', opened);
      await h.services.releases!.refresh();
      await pumpHome(tester, h);
      expect(find.byKey(_banner), findsOneWidget);
      expect(find.text('Yangi versiya mavjud'), findsOneWidget);
      expect(find.byKey(_notice), findsNothing);

      await tester.tap(buttonLabeled('Yangilash'));
      await tester.pump();
      expect(
        opened.single.toString(),
        endsWith('/waiter/android/rizqim-waiter-0.2.0.apk'),
      );

      await tapButton(tester, 'Keyinroq');
      expect(find.byKey(_banner), findsNothing);
      expect(h.services.updatePrefs.dismissedOn, '2026-10-22');

      // The same day, the app opened again: still hidden
      await tester.pumpWidget(const SizedBox.shrink());
      await pumpWaiter(tester, h);
      expect(find.byKey(_banner), findsNothing);

      // Dismissed yesterday: the banner is back
      await h.services.updatePrefs.dismiss('2026-10-21');
      await tester.pumpWidget(const SizedBox.shrink());
      await pumpWaiter(tester, h);
      expect(find.byKey(_banner), findsOneWidget);
    },
  );

  testWidgets(
    'update_required: start notice closes with "Davom etish", the phone works; the red banner is not dismissible',
    (WidgetTester tester) async {
      final WaiterHarness h = _phone('update_required', <Uri>[]);
      await h.services.releases!.refresh();
      await pumpHome(tester, h);

      expect(find.byKey(_notice), findsOneWidget);
      expect(find.text('Ilovani yangilash kerak'), findsWidgets);
      await tester.tap(find.byKey(_continue));
      await tester.pumpAndSettle();
      expect(find.byKey(_notice), findsNothing);

      expect(find.byType(RoomsPage), findsOneWidget);
      expect(find.byKey(_banner), findsOneWidget);
      expect(find.text('Keyinroq'), findsNothing);
    },
  );

  testWidgets('pairing 426: the message and a download link', (
    WidgetTester tester,
  ) async {
    final List<Uri> opened = <Uri>[];
    final WaiterHarness h = _phone(null, opened);
    h.adapter.respond = (RequestOptions o) {
      if (o.method == 'POST' && o.uri.path.endsWith('/v1/devices/pair')) {
        return const FakeResponse(426, <String, Object?>{
          'success': false,
          'message': 'Ilova eskirgan: yangi versiyani yuklab oling.',
          'data': <String, Object?>{
            'reason': 'app_outdated',
            'current': '0.1.0+1',
            'minimum': '0.2.0+5',
            'url': 'https://pos.rizqim.uz/files/waiter/android/new.apk',
          },
        });
      }
      return null;
    };
    await pumpWaiter(tester, h);
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
    expect(opened.single.toString(), endsWith('/waiter/android/new.apk'));
    expect(find.text('Telefonni ulash'), findsOneWidget);
  });
}
