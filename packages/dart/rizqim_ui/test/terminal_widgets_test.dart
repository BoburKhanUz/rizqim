import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

Widget host(Widget child, {ThemeData? theme}) => MaterialApp(
  theme: theme ?? RzTheme.dark(),
  home: Scaffold(body: Center(child: child)),
);

void main() {
  group('RzPinEntry', () {
    testWidgets('keys are at least 48px and the dots follow the digits', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        host(RzPinEntry(onSubmit: (String pin) async => true)),
      );
      final Size key = tester.getSize(find.bySemanticsLabel('5'));
      expect(key.width, greaterThanOrEqualTo(48));
      expect(key.height, greaterThanOrEqualTo(48));

      await tester.tap(find.bySemanticsLabel('1'));
      await tester.tap(find.bySemanticsLabel('2'));
      await tester.pump();
      expect(find.bySemanticsLabel('PIN: 2 ta raqam terildi'), findsOneWidget);
      await tester.tap(find.bySemanticsLabel("O'chirish"));
      await tester.pump();
      expect(find.bySemanticsLabel('PIN: 1 ta raqam terildi'), findsOneWidget);
    });

    testWidgets(
      'submit is enabled from minLength; the full length submits by itself',
      (WidgetTester tester) async {
        final List<String> submitted = <String>[];
        await tester.pumpWidget(
          host(
            RzPinEntry(
              onSubmit: (String pin) async {
                submitted.add(pin);
                return true;
              },
            ),
          ),
        );
        FilledButton submit() => tester.widget<FilledButton>(
          find.descendant(
            of: find.bySemanticsLabel('Kirish'),
            matching: find.byType(FilledButton),
          ),
        );
        for (final String d in <String>['4', '8', '2']) {
          await tester.tap(find.bySemanticsLabel(d));
        }
        await tester.pump();
        expect(submit().onPressed, isNull);
        await tester.tap(find.bySemanticsLabel('1'));
        await tester.pump();
        expect(submit().onPressed, isNotNull);
        await tester.tap(find.bySemanticsLabel('Kirish'));
        await tester.pumpAndSettle();
        expect(submitted, <String>['4821']);

        // six digits go by themselves
        for (final String d in <String>['9', '7']) {
          await tester.tap(find.bySemanticsLabel(d));
        }
        await tester.pumpAndSettle();
        expect(submitted.last, '482197');
      },
    );

    testWidgets('a refused PIN clears the digits; disabled pad ignores taps', (
      WidgetTester tester,
    ) async {
      final Completer<bool> answer = Completer<bool>();
      await tester.pumpWidget(
        host(RzPinEntry(onSubmit: (String pin) => answer.future)),
      );
      for (final String d in <String>['1', '3', '5', '7', '9', '0']) {
        await tester.tap(find.bySemanticsLabel(d));
      }
      await tester.pump();
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      // busy: taps do nothing
      await tester.tap(find.bySemanticsLabel('2'));
      answer.complete(false);
      await tester.pumpAndSettle();
      expect(find.bySemanticsLabel('PIN: 0 ta raqam terildi'), findsOneWidget);

      await tester.pumpWidget(
        host(RzPinEntry(enabled: false, onSubmit: (String pin) async => true)),
      );
      await tester.tap(find.bySemanticsLabel('2'));
      await tester.pump();
      expect(find.bySemanticsLabel('PIN: 0 ta raqam terildi'), findsOneWidget);
    });

    testWidgets('a hardware keyboard types, deletes and submits', (
      WidgetTester tester,
    ) async {
      final List<String> submitted = <String>[];
      await tester.pumpWidget(
        host(
          RzPinEntry(
            onSubmit: (String pin) async {
              submitted.add(pin);
              return true;
            },
          ),
        ),
      );
      await tester.pump();
      for (final LogicalKeyboardKey k in <LogicalKeyboardKey>[
        LogicalKeyboardKey.digit2,
        LogicalKeyboardKey.digit4,
        LogicalKeyboardKey.digit6,
        LogicalKeyboardKey.digit9,
        LogicalKeyboardKey.backspace,
        LogicalKeyboardKey.digit8,
        LogicalKeyboardKey.enter,
      ]) {
        await tester.sendKeyEvent(k);
      }
      await tester.pumpAndSettle();
      expect(submitted, <String>['2468']);
    });
  });

  testWidgets('RzIdleDetector fires after the timeout and touches reset it', (
    WidgetTester tester,
  ) async {
    var idle = 0;
    await tester.pumpWidget(
      host(
        RzIdleDetector(
          timeout: const Duration(minutes: 5),
          onIdle: () => idle++,
          child: const SizedBox.square(dimension: 200, child: Text('board')),
        ),
      ),
    );
    await tester.pump(const Duration(minutes: 4));
    await tester.tap(find.text('board'));
    await tester.pump(const Duration(minutes: 4));
    expect(idle, 0, reason: 'the tap restarted the countdown');
    await tester.sendKeyEvent(LogicalKeyboardKey.space);
    await tester.pump(const Duration(minutes: 4, seconds: 59));
    expect(idle, 0, reason: 'a key press counts too');
    await tester.pump(const Duration(seconds: 1));
    expect(idle, 1);
    await tester.pump(const Duration(minutes: 10));
    expect(idle, 1, reason: 'fires once');

    // turned off
    await tester.pumpWidget(
      host(
        RzIdleDetector(
          timeout: null,
          onIdle: () => idle++,
          child: const Text('board'),
        ),
      ),
    );
    await tester.pump(const Duration(hours: 1));
    expect(idle, 1);
  });

  testWidgets(
    'RzIdleDetector above the Navigator counts touches inside dialogs',
    (WidgetTester tester) async {
      var idle = 0;
      await tester.pumpWidget(
        MaterialApp(
          theme: RzTheme.dark(),
          builder: (BuildContext context, Widget? child) => RzIdleDetector(
            timeout: const Duration(minutes: 5),
            onIdle: () => idle++,
            child: child!,
          ),
          home: Builder(
            builder: (BuildContext context) => Scaffold(
              body: Center(
                child: TextButton(
                  onPressed: () => showDialog<void>(
                    context: context,
                    builder: (BuildContext context) => const Dialog(
                      child: SizedBox.square(
                        dimension: 300,
                        child: Center(child: Text('in dialog')),
                      ),
                    ),
                  ),
                  child: const Text('open'),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();
      for (var i = 0; i < 3; i++) {
        await tester.pump(const Duration(minutes: 4));
        await tester.tap(find.text('in dialog'));
      }
      expect(
        idle,
        0,
        reason: 'touches inside the dialog restart the countdown',
      );
      await tester.pump(const Duration(minutes: 5));
      expect(idle, 1);
    },
  );

  testWidgets('RzOfflineBanner shows the message and the action', (
    WidgetTester tester,
  ) async {
    var retried = 0;
    for (final ThemeData theme in <ThemeData>[
      RzTheme.light(),
      RzTheme.dark(),
    ]) {
      await tester.pumpWidget(
        host(
          RzOfflineBanner(
            title: "Aloqa yo'q",
            message: 'Oxirgi yangilanish 14:05',
            actionLabel: 'Qayta urinish',
            onAction: () => retried++,
          ),
          theme: theme,
        ),
      );
      expect(find.textContaining("Aloqa yo'q"), findsOneWidget);
      await tester.tap(find.text('Qayta urinish'));
    }
    expect(retried, 2);
    const RzColors c = RzColors.dark;
    expect(contrastRatio(c.onDanger, c.danger), greaterThanOrEqualTo(4.5));
    expect(
      contrastRatio(RzColors.light.onDanger, RzColors.light.danger),
      greaterThanOrEqualTo(4.5),
    );
  });

  testWidgets('RzStaffTile: initials, 72px high, tap', (
    WidgetTester tester,
  ) async {
    expect(RzStaffTile.initials('Aziz Karimov'), 'AK');
    expect(RzStaffTile.initials('  oybek '), 'O');
    expect(RzStaffTile.initials(''), '?');
    var taps = 0;
    await tester.pumpWidget(
      host(
        SizedBox(
          width: 280,
          child: RzStaffTile(
            name: 'Aziz Karimov',
            subtitle: 'Oshpaz',
            onTap: () => taps++,
          ),
        ),
      ),
    );
    expect(find.text('AK'), findsOneWidget);
    expect(
      tester.getSize(find.byType(RzStaffTile)).height,
      greaterThanOrEqualTo(72),
    );
    await tester.tap(find.byType(RzStaffTile));
    expect(taps, 1);
  });
}
