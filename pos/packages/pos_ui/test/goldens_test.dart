import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/scenes.dart';

// Golden images of the components in both themes. Text renders in the test font (boxes): the images check layout,
// colours and states, not glyphs. After an intended change: flutter test --update-goldens test/goldens_test.dart
void main() {
  final Map<String, (Widget Function(), Size)> scenes =
      <String, (Widget Function(), Size)>{
        'tiles': (tilesScene, const Size(520, 260)),
        'amount_entry': (amountScene, const Size(340, 560)),
        'quantity_keypad': (quantityScene, const Size(640, 660)),
        'floor_map': (floorScene, const Size(900, 560)),
        'room_summary': (summaryScene, const Size(480, 420)),
      };

  for (final MapEntry<String, (Widget Function(), Size)> scene
      in scenes.entries) {
    for (final (String mode, ThemeData theme) in <(String, ThemeData)>[
      ('light', RzTheme.light()),
      ('dark', RzTheme.dark()),
    ]) {
      testWidgets('${scene.key} ($mode)', (WidgetTester tester) async {
        setScreen(tester, scene.value.$2);
        await tester.pumpWidget(host(scene.value.$1(), theme: theme));
        await tester.pumpAndSettle();
        await expectLater(
          find.byType(Scaffold),
          matchesGoldenFile('goldens/${scene.key}_$mode.png'),
        );
      });
    }
  }
}
