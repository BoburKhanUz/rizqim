import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/scenes.dart';

/// M5 S5: the update banner and the corner mark. Only a notice: "Davom etish" stays with the app.
PosAppRelease rel(PosReleaseState s) => PosAppRelease(
  state: s,
  current: '1.0.0+1',
  latest: '1.4.0+7',
  minimum: '1.2.0+5',
  url: 'https://pos.rizqim.uz/a.apk',
  notes: 'Yangi chek dizayni',
);

PosUpdateGate closed = canUpdateNow(
  pendingOperations: 2,
  paymentInProgress: false,
);

void main() {
  testWidgets('update_available: text, notes, Yangilash and a dismiss button', (
    WidgetTester tester,
  ) async {
    int updates = 0;
    int dismissed = 0;
    await tester.pumpWidget(
      host(
        PosUpdateBanner(
          release: rel(PosReleaseState.updateAvailable),
          gate: PosUpdateGate.open,
          onUpdate: () => updates++,
          onDismiss: () => dismissed++,
        ),
      ),
    );
    expect(find.text('Yangi versiya mavjud'), findsOneWidget);
    expect(find.textContaining('1.4.0+7 versiyasi chiqdi'), findsOneWidget);
    expect(find.textContaining('Yangi chek dizayni'), findsOneWidget);
    await tester.tap(find.text('Yangilash'));
    await tester.tap(find.text('Keyinroq'));
    expect(updates, 1);
    expect(dismissed, 1);
  });

  testWidgets('update_required: red and not dismissible', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      host(
        PosUpdateBanner(
          release: rel(PosReleaseState.updateRequired),
          gate: PosUpdateGate.open,
          onUpdate: () {},
          onDismiss: () {},
        ),
      ),
    );
    expect(find.text('Ilovani yangilash kerak'), findsOneWidget);
    expect(find.textContaining('Eng kam versiya: 1.2.0+5'), findsOneWidget);
    expect(find.text('Keyinroq'), findsNothing);
    final Material m = tester.widget<Material>(
      find.byKey(const ValueKey<String>('pos-update-banner')),
    );
    final BuildContext ctx = tester.element(find.byType(PosUpdateBanner));
    expect(m.color, ctx.rz.danger);
  });

  testWidgets('update_available without onDismiss has no dismiss button', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      host(
        PosUpdateBanner(
          release: rel(PosReleaseState.updateAvailable),
          gate: PosUpdateGate.open,
          onUpdate: () {},
        ),
      ),
    );
    expect(find.text('Keyinroq'), findsNothing);
  });

  testWidgets(
    'Yangilash is disabled and shows the reason while the gate is closed',
    (WidgetTester tester) async {
      int updates = 0;
      await tester.pumpWidget(
        host(
          PosUpdateBanner(
            release: rel(PosReleaseState.updateRequired),
            gate: closed,
            onUpdate: () => updates++,
          ),
        ),
      );
      expect(find.text(PosUpdateReasons.queued), findsOneWidget);
      await tester.tap(find.text('Yangilash'), warnIfMissed: false);
      expect(updates, 0);
      final RzButton b = tester.widget<RzButton>(find.byType(RzButton).first);
      expect(b.onPressed, isNull);
    },
  );

  testWidgets('a payment in progress gives its own reason', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      host(
        PosUpdateBanner(
          release: rel(PosReleaseState.updateAvailable),
          gate: canUpdateNow(pendingOperations: 0, paymentInProgress: true),
          onUpdate: () {},
        ),
      ),
    );
    expect(find.text(PosUpdateReasons.paying), findsOneWidget);
  });

  testWidgets('the ok state shows nothing', (WidgetTester tester) async {
    await tester.pumpWidget(
      host(
        Column(
          children: <Widget>[
            PosUpdateBanner(
              release: rel(PosReleaseState.ok),
              gate: PosUpdateGate.open,
              onUpdate: () {},
            ),
            PosUpdateMark(release: rel(PosReleaseState.ok)),
          ],
        ),
      ),
    );
    expect(
      find.byKey(const ValueKey<String>('pos-update-banner')),
      findsNothing,
    );
    expect(find.byKey(const ValueKey<String>('pos-update-mark')), findsNothing);
  });

  testWidgets('the corner mark is quiet for available and red for required', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      host(PosUpdateMark(release: rel(PosReleaseState.updateAvailable))),
    );
    final BuildContext ctx = tester.element(find.byType(PosUpdateMark));
    DecoratedBox box() => tester.widget<DecoratedBox>(
      find.byKey(const ValueKey<String>('pos-update-mark')),
    );
    expect((box().decoration as BoxDecoration).color, ctx.rz.infoSoft);
    expect(find.bySemanticsLabel('Yangilash kerak'), findsOneWidget);

    await tester.pumpWidget(
      host(PosUpdateMark(release: rel(PosReleaseState.updateRequired))),
    );
    expect((box().decoration as BoxDecoration).color, ctx.rz.danger);
  });

  testWidgets(
    '412 px phone: both buttons sit under the text, nothing overflows',
    (WidgetTester tester) async {
      setScreen(tester, const Size(412, 800));
      int dismissed = 0;
      await tester.pumpWidget(
        host(
          PosUpdateBanner(
            release: rel(PosReleaseState.updateAvailable),
            gate: closed,
            onUpdate: () {},
            onDismiss: () => dismissed++,
          ),
        ),
      );
      expect(tester.takeException(), isNull);
      final Rect text = tester.getRect(
        find.textContaining('1.4.0+7 versiyasi'),
      );
      final Rect update = tester.getRect(find.text('Yangilash'));
      final Rect later = tester.getRect(find.text('Keyinroq'));
      expect(update.top, greaterThanOrEqualTo(text.bottom));
      expect(later.top, greaterThanOrEqualTo(text.bottom));
      expect(later.right, lessThanOrEqualTo(412));
      // The text keeps the width of the banner (not squeezed by the buttons)
      expect(text.width, greaterThan(250));
      await tester.tap(find.text('Keyinroq'));
      expect(dismissed, 1);
    },
  );

  testWidgets('wide screen: the buttons stay beside the text', (
    WidgetTester tester,
  ) async {
    setScreen(tester, const Size(1024, 768));
    await tester.pumpWidget(
      host(
        PosUpdateBanner(
          release: rel(PosReleaseState.updateAvailable),
          gate: PosUpdateGate.open,
          onUpdate: () {},
          onDismiss: () {},
        ),
      ),
    );
    final Rect text = tester.getRect(find.textContaining('1.4.0+7 versiyasi'));
    final Rect update = tester.getRect(find.text('Yangilash'));
    expect(update.left, greaterThan(text.right));
  });
}
