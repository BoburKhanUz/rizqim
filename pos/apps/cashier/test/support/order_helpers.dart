import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';

/// Menyudagi taom tugmasi (mahsulot id bo'yicha).
Finder menuTile(int productId) =>
    find.byKey(ValueKey<String>('menu-$productId'));

/// "Yuborish" (asosiy) tugmasi.
final Finder sendButton = find.byKey(const ValueKey<String>('send'));

String som(int value) => PosMoney.som(value).format();

/// Soxta backend buyurtmasining qatorlari.
List<Map<String, Object?>> items(Map<String, Object?> order) =>
    (order['items']! as List<Object?>).cast<Map<String, Object?>>();

/// Zal xaritasida joyni bosib, buyurtma muharririni ochadi.
Future<void> openPlace(WidgetTester tester, String name) async {
  await tester.tap(find.text(name));
  await tester.pumpAndSettle();
}

/// Bosishlar soni (tezlik nishoni: ochiq buyurtmaga 3 taomli bosqich — ≤ 6 bosish).
class TapCounter {
  TapCounter(this.tester);

  final WidgetTester tester;
  int count = 0;

  Future<void> tap(Finder finder) async {
    count++;
    await tester.tap(finder);
    await tester.pumpAndSettle();
  }
}
