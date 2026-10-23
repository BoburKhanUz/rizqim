import 'package:flutter/foundation.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

/// Tanlangan qo'shimcha: modifikator va soni.
class DraftModifier {
  const DraftModifier(this.modifier, this.group, [this.quantity = 1]);

  final PosModifier modifier;
  final PosModifierGroup group;
  final int quantity;

  PosMoney get total => modifier.priceDelta * quantity;
}

/// Yangi bosqichdagi bitta qator (hali yuborilmagan).
class DraftLine {
  const DraftLine({
    required this.product,
    required this.variant,
    required this.quantity,
    this.modifiers = const <DraftModifier>[],
    this.note,
  });

  final PosProduct product;
  final PosVariant variant;
  final PosQuantity quantity;
  final List<DraftModifier> modifiers;
  final String? note;

  /// Bir xil qatorlar (taom, variant, qo'shimchalar, izoh) birlashadi.
  String get key {
    final List<String> mods = <String>[
      for (final DraftModifier m in modifiers)
        '${m.modifier.uuid}x${m.quantity}',
    ]..sort();
    return '${variant.uuid}|${mods.join(',')}|${note?.trim() ?? ''}';
  }

  bool get hasOptions =>
      modifiers.isNotEmpty || (note?.trim().isNotEmpty ?? false);

  /// Bir birlik narxi: variant + qo'shimchalar.
  PosMoney get unitPrice => PosMoney.sum(<PosMoney>[
    variant.price,
    for (final DraftModifier m in modifiers) m.total,
  ]);

  /// Taxminiy summa (server qayta hisoblaydi: xizmat haqi, xona puli).
  PosMoney get total => unitPrice.timesQuantity(quantity);

  String get name => product.variants.length > 1 && variant.name.isNotEmpty
      ? '${product.name} (${variant.name})'
      : product.name;

  DraftLine copyWith({PosQuantity? quantity, String? note}) => DraftLine(
    product: product,
    variant: variant,
    quantity: quantity ?? this.quantity,
    modifiers: modifiers,
    note: note ?? this.note,
  );

  PosLineInput toInput() => PosLineInput(
    variantUuid: variant.uuid,
    quantity: quantity,
    modifiers: <PosModifierChoice>[
      for (final DraftModifier m in modifiers)
        PosModifierChoice(m.modifier.uuid, quantity: m.quantity),
    ],
    note: note,
  );
}

/// Yangi bosqich savati: qatorlar, miqdorlar, izohlar. Faqat xotirada: yuborilguncha serverga hech narsa ketmaydi.
class DraftCart extends ChangeNotifier {
  final List<DraftLine> _lines = <DraftLine>[];

  List<DraftLine> get lines => List<DraftLine>.unmodifiable(_lines);

  bool get isEmpty => _lines.isEmpty;

  int get length => _lines.length;

  PosMoney get total => PosMoney.sum(_lines.map((DraftLine l) => l.total));

  /// Variantning savatdagi umumiy miqdori (barcha qo'shimcha/izoh variantlari bilan).
  PosQuantity quantityOf(PosVariant v) => _lines
      .where((DraftLine l) => l.variant.uuid == v.uuid)
      .fold(PosQuantity.zero, (PosQuantity a, DraftLine l) => a + l.quantity);

  /// Variantning qo'shimchasiz, izohsiz qatori miqdori (yo'q — nol).
  PosQuantity plainQuantityOf(PosVariant v) {
    for (final DraftLine l in _lines) {
      if (l.variant.uuid == v.uuid && !l.hasOptions) return l.quantity;
    }
    return PosQuantity.zero;
  }

  /// Mahsulotning savatdagi qatorlari soni.
  int linesOf(PosProduct p) =>
      _lines.where((DraftLine l) => l.product.id == p.id).length;

  /// Qo'shish: bir xil qator bo'lsa miqdori qo'shiladi.
  void add(DraftLine line) {
    final int i = _lines.indexWhere((DraftLine l) => l.key == line.key);
    if (i < 0) {
      _lines.add(line);
    } else {
      _lines[i] = _lines[i].copyWith(
        quantity: _lines[i].quantity + line.quantity,
      );
    }
    notifyListeners();
  }

  /// Oddiy (qo'shimchasiz, izohsiz) qatorning miqdorini o'rnatadi; nol — olib tashlaydi.
  void setPlainQuantity(PosProduct p, PosVariant v, PosQuantity quantity) {
    final DraftLine plain = DraftLine(
      product: p,
      variant: v,
      quantity: quantity,
    );
    final int i = _lines.indexWhere((DraftLine l) => l.key == plain.key);
    if (!quantity.isPositive) {
      if (i >= 0) _lines.removeAt(i);
    } else if (i >= 0) {
      _lines[i] = plain;
    } else {
      _lines.add(plain);
    }
    notifyListeners();
  }

  void setQuantity(DraftLine line, PosQuantity quantity) {
    final int i = _lines.indexOf(line);
    if (i < 0) return;
    if (!quantity.isPositive) {
      _lines.removeAt(i);
    } else {
      _lines[i] = line.copyWith(quantity: quantity);
    }
    notifyListeners();
  }

  void setNote(DraftLine line, String? note) {
    final int i = _lines.indexOf(line);
    if (i < 0) return;
    final String? clean = (note == null || note.trim().isEmpty)
        ? null
        : note.trim();
    final DraftLine next = DraftLine(
      product: line.product,
      variant: line.variant,
      quantity: line.quantity,
      modifiers: line.modifiers,
      note: clean,
    );
    final int same = _lines.indexWhere(
      (DraftLine l) => !identical(l, line) && l.key == next.key,
    );
    if (same >= 0) {
      _lines[same] = _lines[same].copyWith(
        quantity: _lines[same].quantity + next.quantity,
      );
      _lines.removeAt(i);
    } else {
      _lines[i] = next;
    }
    notifyListeners();
  }

  void remove(DraftLine line) {
    _lines.remove(line);
    notifyListeners();
  }

  void clear() {
    _lines.clear();
    notifyListeners();
  }

  List<PosLineInput> toInputs() => <PosLineInput>[
    for (final DraftLine l in _lines) l.toInput(),
  ];
}
