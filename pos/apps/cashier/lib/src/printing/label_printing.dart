import 'dart:async';

import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';

import '../features/order/kitchen_tickets.dart';
import '../features/order/takeaway_widgets.dart' show pickupLocalTime;
import 'printing_service.dart';

/// Stiker yoki yorliq chiqadigan bitta taom (buyurtma qatori, kombo tarkibi yoki onlayn buyurtma qatori).
class LabelDish {
  const LabelDish({
    required this.name,
    required this.quantity,
    this.variantName,
    this.modifiers = const <String>[],
    this.categoryId,
    this.context,
    this.measured = false,
  });

  final String name;
  final String? variantName;
  final List<String> modifiers;

  /// Necha dona (kombo tarkibida — kombo soni × tarkib donasi).
  final PosQuantity quantity;
  final int? categoryId;

  /// Kombo ichida — kombo nomi.
  final String? context;
  final bool measured;
}

/// Bitta buyurtmaning stikerlari va paket yorlig'i (chop etishdan oldin ko'rish ham mumkin).
class LabelBatch {
  const LabelBatch({
    required this.size,
    this.stickers = const <CupSticker>[],
    this.bag,
  });

  final LabelSize size;
  final List<CupSticker> stickers;
  final BagLabel? bag;

  bool get isEmpty => stickers.isEmpty && bag == null;
  int get count => stickers.length + (bag == null ? 0 : 1);

  List<LabelLayout> get layouts => <LabelLayout>[
    for (final CupSticker s in stickers) layoutCupSticker(s, size),
    if (bag != null) layoutBagLabel(bag!, size),
  ];
}

/// P6-32 stakan stikeri va paket yorlig'i (docs/POS_SPEC.md "Tez modifikatorlar va stiker"): filial sozlamasi
/// (`labels` bootstrap'da) bo'yicha qaysi taomga stiker chiqadi ([PosLabelSettings.appliesTo]) va nechta
/// ([PosLabelSettings.stickersFor]); kombo ichidagi ichimlik ham (tarkib qatori, kombo nomi bilan). Olib ketish va
/// Telegram olib ketish buyurtmasiga paket yorlig'i (`bag_label`). Printer — bootstrap'dagi `label` printeri (TSPL).
///
/// Chop etish to'lovni hech qachon to'xtatmaydi: hammasi navbatga qo'yiladi (qayta urinish bilan), chiqmasa
/// "Stiker chop etilmadi" banneri ([issues]) "Qayta urinish" bilan; printer yoki sozlama bo'lmasa — jim.
class CashierLabels {
  CashierLabels({
    required this.printing,
    required this.bootstrap,
    required this.catalog,
    required this.now,
  });

  final CashierPrinting printing;
  final PosBootstrap? Function() bootstrap;

  /// Kategoriyani bilish uchun (stiker faqat sozlangan kategoriyalarga).
  final PosCatalog? Function() catalog;
  final DateTime Function() now;

  /// Filial sozlamasi; `labels` moduli o'chiq — `null`.
  PosLabelSettings? get settings => bootstrap()?.labelSettings;
  PrinterConfig? get printer => printing.labelPrinter;

  /// Stiker chiqadimi (sozlama ham, printer ham bor).
  bool get enabled => settings != null && printer != null;

  KitchenPrintIssues get issues => KitchenPrintIssues.labelsOf(printing);

  // --- yig'ish ---------------------------------------------------------------------------------------------------

  /// Kassa buyurtmasi: [items] (standart — hamma tirik qator) stikerlari va olib ketishda paket yorlig'i ([bag]).
  LabelBatch forOrder(
    PosOrder order, {
    Iterable<PosOrderItem>? items,
    bool bag = true,
    int? number,
  }) {
    final PosLabelSettings? s = settings;
    if (s == null) return const LabelBatch(size: LabelSize.mm40x30);
    final List<PosOrderItem> lines = <PosOrderItem>[
      for (final PosOrderItem i in items ?? order.items)
        if (!i.isVoid) i,
    ];
    final List<LabelDish> dishes = <LabelDish>[
      for (final PosOrderItem i in lines) ...dishesOf(i),
    ];
    final String? plate = order.tableLabel?.trim();
    final bool takeaway =
        order.posChannel == PosChannel.takeaway &&
        (plate == null || plate.isEmpty);
    return _batch(
      s,
      dishes,
      number: '${order.queue?.number ?? number ?? ''}',
      code: order.shortCode,
      takeawayLabel: takeaway
          ? 'Olib ketish'
          : (plate != null && plate.isNotEmpty ? 'Stol $plate' : 'Zalda'),
      bag: bag && takeaway && s.bagLabel,
      pickupAt: order.pickup == null ? null : pickupLocalTime(order.pickup!),
    );
  }

  /// Telegram yoki Mini App olib ketish buyurtmasi (kassir qabul qilganda): mijoz ismi bilan.
  LabelBatch forOnline(PosOnlineOrder order) {
    final PosLabelSettings? s = settings;
    if (s == null) return const LabelBatch(size: LabelSize.mm40x30);
    final List<LabelDish> dishes = <LabelDish>[];
    for (final Object? raw
        in order.raw['items'] as List<Object?>? ?? const []) {
      if (raw is! Map) continue;
      final PosJson j = PosJson.of(raw);
      final PosQuantity qty = _quantityOf(j['quantity']);
      final List<PosComboPart>? parts = PosComboPart.listOf(j['combo_parts']);
      final String name =
          j.strOrNull('product_name') ?? j.strOrNull('name') ?? '';
      if (parts != null) {
        for (final PosComboPart p in parts) {
          dishes.add(_partDish(p, qty, name));
        }
        continue;
      }
      dishes.add(
        LabelDish(
          name: name,
          variantName: j.strOrNull('variant_name'),
          quantity: qty,
          modifiers: <String>[
            for (final Object? m in j.list('modifiers'))
              if (m is Map && m['name'] != null) '${m['name']}',
          ],
          categoryId:
              _categoryOfProduct(j.intOrNull('product_id')) ??
              _categoryByName(name),
        ),
      );
    }
    final Object? queue = order.raw['queue'];
    final Object? when = order.raw['pickup'];
    final String number = queue is Map && queue['number'] != null
        ? '${queue['number']}'
        : '';
    final bool pickup = order.type == 'pickup';
    return _batch(
      s,
      dishes,
      number: number,
      code: order.shortCode,
      customer: order.customerName,
      takeawayLabel: pickup ? 'Olib ketish' : 'Yetkazish',
      bag: s.bagLabel && (pickup || order.type == 'delivery'),
      bagLabel: pickup ? 'Olib ketish' : 'Yetkazish',
      pickupAt: when is Map && when['pickup_at'] != null
          ? pickupLocalTime(PosPickupTime.fromJson(PosJson.of(when)))
          : DateTime.tryParse('${order.raw['pickup_at'] ?? ''}')?.toLocal(),
    );
  }

  /// Buyurtma qatoridan stikerga taomlar: kombo — tarkibi (kombo nomi bilan), oddiy taom — o'zi.
  List<LabelDish> dishesOf(PosOrderItem i) {
    final List<PosComboPart>? parts = i.comboParts;
    if (parts != null && parts.isNotEmpty) {
      return <LabelDish>[
        for (final PosComboPart p in parts)
          _partDish(p, i.quantity, i.productName),
      ];
    }
    return <LabelDish>[
      LabelDish(
        name: i.productName,
        // A dish with one variant ("Porsiya") says nothing more on the cup
        variantName: _variantsOf(i.productVariantId) > 1 ? i.variantName : null,
        quantity: i.quantity,
        modifiers: <String>[
          for (final PosOrderItemModifier m in i.modifiers)
            m.quantity > 1 ? '${m.name} x${m.quantity}' : m.name,
        ],
        categoryId: _categoryOfVariant(i.productVariantId),
        measured: i.unit.isMeasured,
      ),
    ];
  }

  LabelDish _partDish(PosComboPart p, PosQuantity combos, String comboName) =>
      LabelDish(
        name: p.name,
        variantName: p.variantName,
        quantity: combos.isWhole
            ? PosQuantity.milli(combos.milli * p.quantity)
            : PosQuantity.whole(p.quantity),
        modifiers: <String>[
          for (final PosComboModifier m in p.modifiers)
            m.quantity > 1 ? '${m.name} x${m.quantity}' : m.name,
        ],
        categoryId: _categoryOfProduct(p.productId) ?? _categoryByName(p.name),
        context: comboName,
      );

  LabelBatch _batch(
    PosLabelSettings s,
    List<LabelDish> dishes, {
    required String number,
    required String takeawayLabel,
    required bool bag,
    String? code,
    String? customer,
    String bagLabel = 'Olib ketish',
    DateTime? pickupAt,
  }) {
    final DateTime at = now().toLocal();
    final List<CupSticker> stickers = <CupSticker>[];
    var pieces = 0;
    for (final LabelDish d in dishes) {
      pieces += d.measured || !d.quantity.isWhole ? 1 : d.quantity.wholePart;
      if (!s.appliesTo(d.categoryId)) continue;
      final int total = d.measured ? 1 : s.stickersFor(d.quantity);
      for (var n = 1; n <= total; n++) {
        stickers.add(
          CupSticker(
            number: number,
            itemName: d.name,
            variantName: d.variantName,
            modifiers: d.modifiers,
            customerName: customer,
            takeawayLabel: takeawayLabel,
            printedAt: at,
            index: n,
            total: total,
            context: d.context,
          ),
        );
      }
    }
    return LabelBatch(
      size: s.size,
      stickers: stickers,
      bag: bag && pieces > 0
          ? BagLabel(
              number: number.isNotEmpty ? number : '#${code ?? ''}',
              itemCount: pieces,
              printedAt: at,
              orderLabel: bagLabel,
              customerName: customer,
              pickupAt: pickupAt,
            )
          : null,
    );
  }

  int? _categoryOfVariant(int? variantId) {
    if (variantId == null) return null;
    for (final PosProduct p in catalog()?.products ?? const <PosProduct>[]) {
      for (final PosVariant v in p.variants) {
        if (v.id == variantId) return p.categoryId;
      }
    }
    return null;
  }

  int _variantsOf(int? variantId) {
    for (final PosProduct p in catalog()?.products ?? const <PosProduct>[]) {
      if (p.variants.any((PosVariant v) => v.id == variantId)) {
        return p.variants.length;
      }
    }
    return 2;
  }

  /// Onlayn qatorda taom id'si yo'q: menyudagi nomi bo'yicha.
  int? _categoryByName(String name) {
    for (final PosProduct p in catalog()?.products ?? const <PosProduct>[]) {
      if (p.name == name) return p.categoryId;
    }
    return null;
  }

  int? _categoryOfProduct(int? productId) =>
      productId == null ? null : catalog()?.product(productId)?.categoryId;

  static PosQuantity _quantityOf(Object? raw) =>
      PosQuantity.tryParse(raw) ?? PosQuantity.one;

  // --- chop etish ------------------------------------------------------------------------------------------------

  /// Yorliqlarni navbatga qo'yadi; qaytaradi — navbatga tushgani. Hech qachon xato tashlamaydi: printer yo'q — 0,
  /// chizib bo'lmadi — bannerda.
  Future<int> print(LabelBatch batch) async {
    final PrinterConfig? p = printer;
    if (p == null || batch.isEmpty) return 0;
    final LabelPrintOptions options = LabelPrintOptions(textMode: p.textMode);
    var queued = 0;
    for (final LabelLayout layout in batch.layouts) {
      try {
        final PrintJob job = await printing.printers.printLabel(
          p,
          layout,
          options: options,
        );
        issues.watch(printing.printers.queue, job);
        queued++;
      } on Object catch (e) {
        issues.reportRender(layout.title, '$e');
      }
    }
    return queued;
  }

  /// [print] kutmasdan (to'lov va "Keyingi mijoz" kutmaydi).
  void printLater(LabelBatch batch) {
    if (!enabled || batch.isEmpty) return;
    unawaited(print(batch));
  }

  /// Yangi oshxonaga ketgan qatorlar stikerlari ([KitchenTickets.printSent] kabi: [before] da yuborilmagan, [after]
  /// da yuborilgan); paket yorlig'i — buyurtmaning birinchi yuborilishida.
  void printSent(PosOrder? before, PosOrder after) {
    if (!enabled) return;
    final Set<int> sent = <int>{
      for (final PosOrderItem i in before?.items ?? const <PosOrderItem>[])
        if (i.wasSent) i.id,
    };
    final List<PosOrderItem> fresh = <PosOrderItem>[
      for (final PosOrderItem i in after.items)
        if (i.wasSent && !i.isVoid && !sent.contains(i.id)) i,
    ];
    if (fresh.isEmpty) return;
    printLater(forOrder(after, items: fresh, bag: sent.isEmpty));
  }
}
