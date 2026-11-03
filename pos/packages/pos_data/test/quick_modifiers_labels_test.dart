import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6-32: modifier group display hints and the preselected option (`quick_modifiers`), and the branch's sticker and
/// label settings (`labels`, the bootstrap block).
void main() {
  Map<String, Object?> modifier(
    int id,
    String name, {
    bool isDefault = false,
    bool? isAvailable,
    String delta = '0.00',
  }) => <String, Object?>{
    'id': id,
    'uuid': 'm-$id',
    'name': name,
    'price_delta': delta,
    'max_quantity': 1,
    'is_default': isDefault,
    'is_available': ?isAvailable,
  };

  Map<String, Object?> groupJson({
    String? mode,
    int? max = 1,
    List<Map<String, Object?>>? modifiers,
  }) => <String, Object?>{
    'id': 1,
    'uuid': 'g-1',
    'name': 'Hajm',
    'min_select': 1,
    'max_select': max,
    'is_required': true,
    'sort_order': 0,
    'display_mode': ?mode,
    'modifiers':
        modifiers ??
        <Map<String, Object?>>[
          modifier(1, 'Kichik'),
          modifier(2, "O'rta", isDefault: true, delta: '5000.00'),
          modifier(3, 'Katta', delta: '9000.00'),
        ],
  };

  PosModifierGroup parse(Map<String, Object?> json) =>
      PosModifierGroup.fromJson(PosJson(json));

  group('modifier group hints', () {
    test('display mode and default option are read', () {
      final PosModifierGroup g = parse(groupJson(mode: 'size'));
      expect(g.displayMode, PosModifierDisplay.size);
      expect(g.isQuick, isTrue);
      expect(g.modifiers.map((PosModifier m) => m.isDefault), <bool>[
        false,
        true,
        false,
      ]);
      expect(g.preselected.map((PosModifier m) => m.name), <String>["O'rta"]);
    });

    test(
      'an old server (no hints) means a plain dialog and nothing preselected',
      () {
        final PosModifierGroup g = parse(
          groupJson(
            modifiers: <Map<String, Object?>>[
              <String, Object?>{
                'id': 1,
                'uuid': 'm-1',
                'name': 'Kichik',
                'price_delta': '0.00',
              },
            ],
          ),
        );
        expect(g.displayMode, PosModifierDisplay.dialog);
        expect(g.isQuick, isFalse);
        expect(g.modifiers.single.isDefault, isFalse);
        expect(g.preselected, isEmpty);
      },
    );

    test('an unknown mode falls back to the dialog', () {
      expect(
        parse(groupJson(mode: 'carousel')).displayMode,
        PosModifierDisplay.dialog,
      );
      expect(
        parse(groupJson(mode: 'inline')).displayMode,
        PosModifierDisplay.inline,
      );
    });

    test('a stopped default is not preselected, and defaults never exceed the picks', () {
      final PosModifierGroup stopped = parse(
        groupJson(
          modifiers: <Map<String, Object?>>[
            modifier(1, 'Kichik'),
            modifier(2, "O'rta", isDefault: true, isAvailable: false),
          ],
        ),
      );
      expect(stopped.preselected, isEmpty);

      final PosModifierGroup crowded = parse(
        groupJson(
          max: 2,
          modifiers: <Map<String, Object?>>[
            modifier(1, 'A', isDefault: true),
            modifier(2, 'B', isDefault: true),
            modifier(3, 'C', isDefault: true),
          ],
        ),
      );
      expect(crowded.preselected.map((PosModifier m) => m.name), <String>[
        'A',
        'B',
      ]);
    });

    test('the catalog keeps the hints', () {
      final Map<String, Object?> raw = Map<String, Object?>.from(
        fixtureData('catalog')! as Map<String, Object?>,
      );
      final List<Object?> products = List<Object?>.from(
        raw['products']! as List<Object?>,
      );
      final Map<String, Object?> first = Map<String, Object?>.from(
        products.first! as Map<String, Object?>,
      )..['modifier_groups'] = <Object?>[groupJson(mode: 'size')];
      products[0] = first;
      raw['products'] = products;

      final PosProduct product = PosCatalog.fromJson(PosJson(raw))
          .products
          .first;
      expect(
        product.modifierGroups.single.displayMode,
        PosModifierDisplay.size,
      );
      expect(product.modifierGroups.single.preselected.single.name, "O'rta");
    });
  });

  group('label settings', () {
    Map<String, Object?> labels({
      String size = '40x30',
      String scope = 'all',
      List<int> categories = const <int>[],
      bool perUnit = true,
      bool bag = true,
      bool configured = true,
    }) => <String, Object?>{
      'branch_id': 1,
      'configured': configured,
      'size': size,
      'scope': scope,
      'category_ids': categories,
      'per_unit': perUnit,
      'bag_label': bag,
    };

    PosLabelSettings parseLabels(Map<String, Object?> json) =>
        PosLabelSettings.fromJson(PosJson(json));

    test('size, scope and flags are read', () {
      final PosLabelSettings s = parseLabels(
        labels(
          size: '58x40',
          scope: 'categories',
          categories: <int>[7, 9],
          perUnit: false,
          bag: false,
        ),
      );
      expect(s.size, LabelSize.mm58x40);
      expect(s.allItems, isFalse);
      expect(s.categoryIds, <int>{7, 9});
      expect(s.perUnit, isFalse);
      expect(s.bagLabel, isFalse);
      expect(s.configured, isTrue);
    });

    test('which categories get a sticker', () {
      final PosLabelSettings all = parseLabels(labels());
      expect(all.appliesTo(7), isTrue);
      expect(all.appliesTo(null), isTrue);

      final PosLabelSettings drinks = parseLabels(
        labels(scope: 'categories', categories: <int>[7]),
      );
      expect(drinks.appliesTo(7), isTrue);
      expect(drinks.appliesTo(8), isFalse);
      expect(drinks.appliesTo(null), isFalse);
    });

    test('one sticker per piece, or one per line', () {
      final PosLabelSettings perPiece = parseLabels(labels());
      expect(perPiece.stickersFor(const PosQuantity.whole(3)), 3);
      expect(perPiece.stickersFor(const PosQuantity.whole(1)), 1);
      // a weighed dish is one sticker
      expect(perPiece.stickersFor(PosQuantity.parse('1.5')), 1);

      final PosLabelSettings perLine = parseLabels(labels(perUnit: false));
      expect(perLine.stickersFor(const PosQuantity.whole(3)), 1);
    });

    test('the bootstrap carries the block only with the module', () {
      final Map<String, Object?> raw = Map<String, Object?>.from(
        fixtureData('bootstrap')! as Map<String, Object?>,
      );
      expect(PosBootstrap.fromJson(PosJson(raw)).labelSettings, isNull);

      raw['labels'] = null;
      expect(PosBootstrap.fromJson(PosJson(raw)).labelSettings, isNull);

      raw['labels'] = labels(size: '58x40');
      final PosLabelSettings s = PosBootstrap.fromJson(PosJson(raw))
          .labelSettings!;
      expect(s.size, LabelSize.mm58x40);
      expect(s.bagLabel, isTrue);
    });

    test('an unknown size is the small label', () {
      expect(parseLabels(labels(size: '100x100')).size, LabelSize.mm40x30);
    });
  });
}
