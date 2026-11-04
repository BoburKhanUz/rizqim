import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

/// Records what would go to the server.
class _Repository implements PosDisplayRepository {
  final List<PosDisplayCart> published = <PosDisplayCart>[];

  @override
  Future<bool> publish(PosDisplayCart cart) async {
    published.add(cart);
    return true;
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

PosDisplayCart _cart(String total) =>
    PosDisplayCart(state: PosDisplayScreen.cart, total: PosMoney.parse(total));

void main() {
  // P6 hardware: the till's own second screen (Sunmi D2) hears every publish at once, before the debounce
  test('local listeners get every cart at once; a failing one does not break the feed', () async {
    final _Repository repo = _Repository();
    final DisplayFeed feed = DisplayFeed(
      repo,
      debounce: const Duration(milliseconds: 20),
    );
    final List<PosDisplayCart> local = <PosDisplayCart>[];
    void listener(PosDisplayCart c) => local.add(c);
    feed
      ..addLocalListener((_) => throw StateError('broken screen'))
      ..addLocalListener(listener);
    feed.publish(_cart('1000'));
    feed.publish(_cart('2000'));
    expect(local.map((PosDisplayCart c) => c.total.toApiString()), <String>[
      '1000.00',
      '2000.00',
    ]);
    await feed.flush();
    expect(repo.published, hasLength(1));

    feed.removeLocalListener(listener);
    feed.idle();
    expect(local, hasLength(2));

    // the module is off: neither the server nor the local screen gets the cart
    feed
      ..addLocalListener(listener)
      ..enabled = false
      ..publish(_cart('3000'));
    expect(local, hasLength(2));
    await feed.dispose();
  });
}
