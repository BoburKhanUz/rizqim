import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';
import 'package:rizqim_waiter/src/features/rooms/place_card.dart';
import 'package:rizqim_waiter/src/features/rooms/rooms_controller.dart';

void main() {
  // The test font (Ahem) is much wider than a real one: this name needs two lines on a 360px phone.
  const String longName = 'Katta xona';

  testWidgets('a long room name wraps to two lines and has a tooltip', (
    WidgetTester tester,
  ) async {
    tester.view
      ..physicalSize = const Size(360, 640)
      ..devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      MaterialApp(
        theme: RzTheme.light(),
        home: Scaffold(
          body: Padding(
            padding: const EdgeInsets.all(16),
            child: PlaceCard(
              entry: const PlaceEntry(
                hall: null,
                table: FloorTable(
                  id: 1,
                  name: longName,
                  kind: FloorTableKind.room,
                  statusWire: 'free',
                  orders: <FloorOrder>[],
                  seats: 12,
                ),
              ),
              onTap: () {},
            ),
          ),
        ),
      ),
    );
    final RenderParagraph name = tester.renderObject<RenderParagraph>(
      find.text(longName),
    );
    expect(name.didExceedMaxLines, isFalse);
    expect(find.byTooltip(longName), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
