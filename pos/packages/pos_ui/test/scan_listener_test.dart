import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_ui/pos_ui.dart';

/// A clock the test moves by hand.
class FakeClock {
  Duration now = Duration.zero;

  Duration call() => now;

  void advance(int ms) => now += Duration(milliseconds: ms);
}

void main() {
  group('KeyboardScanDetector', () {
    test('a fast burst ending with Enter is a scan', () {
      final FakeClock clock = FakeClock();
      final KeyboardScanDetector d = KeyboardScanDetector(clock: clock.call);
      for (final String ch in '4780000000017'.split('')) {
        d.addCharacter(ch);
        clock.advance(8);
      }
      expect(d.addTerminator(), '4780000000017');
      expect(d.pending, 0);
    });

    test('human typing (slow keys) is not a scan', () {
      final FakeClock clock = FakeClock();
      final KeyboardScanDetector d = KeyboardScanDetector(clock: clock.call);
      for (final String ch in '12345'.split('')) {
        d.addCharacter(ch);
        clock.advance(140);
      }
      expect(d.addTerminator(), isNull);
    });

    test('a pause drops what came before; short codes are ignored', () {
      final FakeClock clock = FakeClock();
      final KeyboardScanDetector d = KeyboardScanDetector(clock: clock.call);
      d.addCharacter('x');
      clock.advance(500);
      for (final String ch in 'ABC123'.split('')) {
        d.addCharacter(ch);
        clock.advance(5);
      }
      expect(d.addTerminator(), 'ABC123');
      for (final String ch in 'AB'.split('')) {
        d.addCharacter(ch);
        clock.advance(5);
      }
      expect(d.addTerminator(), isNull);
    });

    test('an Enter long after the last character is not a scan', () {
      final FakeClock clock = FakeClock();
      final KeyboardScanDetector d = KeyboardScanDetector(clock: clock.call);
      for (final String ch in '98765'.split('')) {
        d.addCharacter(ch);
        clock.advance(5);
      }
      clock.advance(400);
      expect(d.addTerminator(), isNull);
    });
  });

  group('KeyboardScanListener', () {
    Future<void> type(WidgetTester tester, String code) async {
      for (final String ch in code.split('')) {
        await tester.sendKeyEvent(
          LogicalKeyboardKey(ch.toLowerCase().codeUnitAt(0)),
          character: ch,
        );
      }
      await tester.sendKeyEvent(LogicalKeyboardKey.enter);
    }

    testWidgets('reports scans and swallows the Enter that ended one', (
      WidgetTester tester,
    ) async {
      final List<String> scans = <String>[];
      var pressed = 0;
      final FocusNode button = FocusNode();
      addTearDown(button.dispose);
      await tester.pumpWidget(
        MaterialApp(
          home: KeyboardScanListener(
            onScan: scans.add,
            // every key arrives within the gap: the test clock does not move
            detector: KeyboardScanDetector(clock: () => Duration.zero),
            child: Scaffold(
              body: TextButton(
                focusNode: button,
                onPressed: () => pressed++,
                child: const Text('To\'lash'),
              ),
            ),
          ),
        ),
      );
      button.requestFocus();
      await tester.pump();
      await type(tester, '4780000000017');
      await tester.pump();
      expect(scans, <String>['4780000000017']);
      // the focused button did not get the scanner's Enter
      expect(pressed, 0);
      // a real Enter still presses it
      await tester.sendKeyEvent(LogicalKeyboardKey.enter);
      await tester.pump();
      expect(pressed, 1);
    });

    testWidgets('a focused text field gets the code itself, no scan event', (
      WidgetTester tester,
    ) async {
      final List<String> scans = <String>[];
      final TextEditingController text = TextEditingController();
      addTearDown(text.dispose);
      await tester.pumpWidget(
        MaterialApp(
          home: KeyboardScanListener(
            onScan: scans.add,
            detector: KeyboardScanDetector(clock: () => Duration.zero),
            child: Scaffold(body: TextField(controller: text, autofocus: true)),
          ),
        ),
      );
      await tester.pump();
      await type(tester, 'ABC123');
      await tester.pump();
      expect(scans, isEmpty);
    });

    testWidgets('disabled — nothing is reported', (WidgetTester tester) async {
      final List<String> scans = <String>[];
      await tester.pumpWidget(
        MaterialApp(
          home: KeyboardScanListener(
            enabled: false,
            onScan: scans.add,
            detector: KeyboardScanDetector(clock: () => Duration.zero),
            child: const SizedBox(),
          ),
        ),
      );
      await type(tester, '12345678');
      expect(scans, isEmpty);
    });
  });
}
