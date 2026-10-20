import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

Widget host(Widget child, {ThemeData? theme}) => MaterialApp(
  theme: theme ?? RzTheme.light(),
  home: Scaffold(body: Center(child: child)),
);

void main() {
  group('RzButton', () {
    testWidgets('is at least 44px high (md) and 52px (lg), in both themes', (
      WidgetTester tester,
    ) async {
      for (final ThemeData theme in <ThemeData>[
        RzTheme.light(),
        RzTheme.dark(),
      ]) {
        await tester.pumpWidget(
          host(
            Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                RzButton(
                  key: const Key('md'),
                  label: 'Saqlash',
                  onPressed: () {},
                ),
                RzButton(
                  key: const Key('lg'),
                  label: 'Buyurtma berish',
                  size: RzButtonSize.lg,
                  onPressed: () {},
                ),
                RzButton(
                  key: const Key('ghost'),
                  label: 'Bekor',
                  variant: RzButtonVariant.ghost,
                  onPressed: () {},
                ),
              ],
            ),
            theme: theme,
          ),
        );
        expect(
          tester.getSize(find.byKey(const Key('md'))).height,
          greaterThanOrEqualTo(44),
        );
        expect(
          tester.getSize(find.byKey(const Key('lg'))).height,
          greaterThanOrEqualTo(52),
        );
        expect(
          tester.getSize(find.byKey(const Key('ghost'))).height,
          greaterThanOrEqualTo(44),
        );
      }
    });

    testWidgets('onPressed fires; disabled and loading ignore taps', (
      WidgetTester tester,
    ) async {
      var taps = 0;
      await tester.pumpWidget(
        host(RzButton(label: 'A', onPressed: () => taps++)),
      );
      await tester.tap(find.text('A'));
      expect(taps, 1);

      await tester.pumpWidget(
        host(RzButton(label: 'A', onPressed: () => taps++, loading: true)),
      );
      await tester.tap(find.byType(RzButton));
      expect(taps, 1, reason: 'loading blocks the press');
      expect(find.byType(CircularProgressIndicator), findsOneWidget);

      await tester.pumpWidget(
        host(const RzButton(label: 'A', onPressed: null)),
      );
      await tester.tap(find.byType(RzButton), warnIfMissed: false);
      expect(taps, 1);
    });

    testWidgets('fullWidth fills the row and a long label does not overflow', (
      WidgetTester tester,
    ) async {
      tester.view
        ..physicalSize = const Size(360, 640)
        ..devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(
        host(
          SizedBox(
            width: 200,
            child: RzButton(
              key: const Key('b'),
              label: "Juda uzun yorliqli tugma, qatorga sig'maydigan",
              onPressed: () {},
              fullWidth: true,
              icon: Icons.check,
            ),
          ),
        ),
      );
      expect(tester.getSize(find.byKey(const Key('b'))).width, 200);
      expect(tester.takeException(), isNull);
    });
  });

  group('StatusBadge', () {
    testWidgets('every backend status renders its customer and staff label', (
      WidgetTester tester,
    ) async {
      for (final OrderStatus s in OrderStatus.values) {
        await tester.pumpWidget(host(StatusBadge(status: s.wire)));
        expect(find.text(s.label), findsOneWidget, reason: s.wire);
        await tester.pumpWidget(
          host(StatusBadge(status: s.wire, audience: StatusAudience.staff)),
        );
        expect(find.text(s.staffLabel), findsOneWidget, reason: s.wire);
      }
    });

    testWidgets('colour comes from the tone group, in light and dark', (
      WidgetTester tester,
    ) async {
      for (final (ThemeData theme, RzColors c) in <(ThemeData, RzColors)>[
        (RzTheme.light(), RzColors.light),
        (RzTheme.dark(), RzColors.dark),
      ]) {
        final Map<String, Color> expectedBg = <String, Color>{
          'new': c.warningSoft,
          'preparing': c.infoSoft,
          'delivered': c.successSoft,
          'canceled': c.dangerSoft,
          'refunded': c.surface2,
          'teleported': c.surface2,
        };
        for (final MapEntry<String, Color> e in expectedBg.entries) {
          await tester.pumpWidget(
            host(StatusBadge(status: e.key), theme: theme),
          );
          await tester.pumpAndSettle(); // AnimatedTheme
          final DecoratedBox box = tester.widget<DecoratedBox>(
            find.descendant(
              of: find.byType(StatusBadge),
              matching: find.byType(DecoratedBox),
            ),
          );
          expect(
            (box.decoration as BoxDecoration).color,
            e.value,
            reason: e.key,
          );
        }
      }
    });

    testWidgets('statuses of one colour group differ by icon', (
      WidgetTester tester,
    ) async {
      final Set<IconData> info = <IconData>{
        for (final OrderStatus s in OrderStatus.values.where(
          (OrderStatus s) =>
              s.tone == StatusTone.info && s != OrderStatus.delivery,
        ))
          StatusBadge.iconOf(s),
      };
      expect(
        info.length,
        5,
        reason: 'accepted, preparing, ready, courier_assigned, on_the_way each have their own icon',
      );
      expect(
        StatusBadge.iconOf(OrderStatus.delivery),
        StatusBadge.iconOf(OrderStatus.onTheWay),
      );
      expect(
        StatusBadge.iconOf(OrderStatus.canceled),
        isNot(StatusBadge.iconOf(OrderStatus.rejected)),
      );
    });

    testWidgets('an unknown status keeps its own name, neutral', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(host(const StatusBadge(status: 'teleported')));
      expect(find.text('teleported'), findsOneWidget);
      expect(find.byIcon(Icons.help_outline), findsOneWidget);
    });

    testWidgets('text is never smaller than 12', (WidgetTester tester) async {
      await tester.pumpWidget(host(const StatusBadge(status: 'new')));
      expect(
        tester.widget<Text>(find.text('Kutilmoqda')).style!.fontSize,
        greaterThanOrEqualTo(12),
      );
    });
  });

  group('EmptyState / InlineError', () {
    testWidgets('EmptyState shows the title, message and one action', (
      WidgetTester tester,
    ) async {
      var pressed = 0;
      await tester.pumpWidget(
        host(
          EmptyState(
            title: "Buyurtmalar yo'q",
            message: 'Yangi buyurtma kelganda shu yerda chiqadi.',
            actionLabel: 'Yangilash',
            onAction: () => pressed++,
          ),
        ),
      );
      expect(find.text("Buyurtmalar yo'q"), findsOneWidget);
      expect(
        find.text('Yangi buyurtma kelganda shu yerda chiqadi.'),
        findsOneWidget,
      );
      await tester.tap(find.text('Yangilash'));
      expect(pressed, 1);
    });

    testWidgets(
      'EmptyState without an action shows no button; it is centred on a tablet',
      (WidgetTester tester) async {
        tester.view
          ..physicalSize = const Size(1280, 800)
          ..devicePixelRatio = 1;
        addTearDown(tester.view.reset);
        await tester.pumpWidget(host(const EmptyState(title: "Bo'sh")));
        expect(find.byType(RzButton), findsNothing);
        expect(tester.getCenter(find.text("Bo'sh")).dx, closeTo(640, 1));
      },
    );

    testWidgets('InlineError retries; without a callback there is no button', (
      WidgetTester tester,
    ) async {
      var retries = 0;
      await tester.pumpWidget(host(InlineError(onRetry: () => retries++)));
      expect(find.text("Ma'lumotni yuklab bo'lmadi."), findsOneWidget);
      await tester.tap(find.text('Qayta urinish'));
      expect(retries, 1);

      await tester.pumpWidget(host(const InlineError(message: 'Tarmoq yo\'q')));
      expect(find.text('Qayta urinish'), findsNothing);
      expect(find.text("Tarmoq yo'q"), findsOneWidget);
    });
  });
}
