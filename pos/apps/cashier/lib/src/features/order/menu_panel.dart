import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../menu_day/menu_day.dart';
import 'order_draft.dart';
import 'order_format.dart';
import 'quick_modifiers.dart';

/// Kategoriya rangi (tugma chetidagi chiziq): kategoriyalar tartibi bo'yicha.
const List<Color> _accents = <Color>[
  Color(0xFFC2410C),
  Color(0xFF2563EB),
  Color(0xFF15803D),
  Color(0xFF9333EA),
  Color(0xFFB45309),
  Color(0xFF0E7490),
  Color(0xFFBE123C),
  Color(0xFF4D7C0F),
];

/// Menyu: qidiruv, "tez-tez" paneli, kategoriyalar va katta taom tugmalari (narx, qozon qoldig'i, "Tugadi").
class MenuPanel extends StatefulWidget {
  const MenuPanel({
    super.key,
    required this.catalog,
    required this.onTap,
    required this.onLongPress,
    this.frequent = const <PosProduct>[],
    this.enabled = true,
    this.columns,
    this.onQuickPick,
  });

  final PosCatalog catalog;
  final ValueChanged<PosProduct> onTap;

  /// P6-32: hajm tugmasi bosildi (taom shu hajm bilan, bir bosishda); `null` — tugmada hajmlar ko'rsatilmaydi.
  final void Function(PosProduct product, PosModifier size)? onQuickPick;

  /// Uzoq bosish: qatorni to'liq sozlab qo'shish (qo'shimcha, izoh, o'rin).
  final ValueChanged<PosProduct> onLongPress;
  final List<PosProduct> frequent;
  final bool enabled;

  /// P6-12 telefon rejimi: ustunlar soni (1 — bir ustunli panjara, katta tugmalar); `null` — eni bo'yicha.
  final int? columns;

  @override
  State<MenuPanel> createState() => _MenuPanelState();
}

class _MenuPanelState extends State<MenuPanel> {
  int? _category;
  final TextEditingController _search = TextEditingController();

  @override
  void initState() {
    super.initState();
    _search.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Color _accentOf(int categoryId) {
    final int i = widget.catalog.categories.indexWhere(
      (PosCategory c) => c.id == categoryId,
    );
    return _accents[(i < 0 ? 0 : i) % _accents.length];
  }

  String? _badge(PosProduct p) {
    final PosVariant? v = p.singleVariant ?? p.variants.firstOrNull;
    if (v == null) return null;
    final PosBatch? b = widget.catalog.batchFor(v);
    if (b != null) return 'Qoldi: ${b.quantityLeft.format()}';
    return null;
  }

  String _subtitle(PosProduct p) {
    final PosVariant? single = p.singleVariant;
    if (single != null) return variantPriceLabel(single);
    if (p.variants.isEmpty) return '';
    final PosVariant cheapest = p.variants.reduce(
      (PosVariant a, PosVariant b) => a.price <= b.price ? a : b,
    );
    return '${variantPriceLabel(cheapest)} dan';
  }

  Widget _tile(PosProduct p) {
    final PosCatalog cat = widget.catalog;
    final MenuDayItem day = menuDayItem(cat, p, menuModulesOf(context));
    final bool available = cat.isProductAvailable(p);
    PosUnavailableReason? reason;
    if (!available) {
      reason = cat.availability.stoppedProducts.contains(p.id)
          ? PosUnavailableReason.stopped
          : (p.variants.isEmpty
                ? null
                : cat.unavailableReasonOf(p.variants.first));
    }
    // P6-15: a sold-out dish with a planned batch says when it comes back instead of its price
    final PosNextBatch? next = available ? null : day.nextBatch;
    final Widget tile = PosTileButton(
      key: ValueKey<String>('menu-${p.id}'),
      label: p.name,
      subtitle: next?.text ?? _subtitle(p),
      // P6-31: a combo tells its default parts
      detail: p.isCombo ? _comboDetail(p) : day.partsLine,
      badge: _badge(p),
      accent: _accentOf(p.categoryId),
      unavailable: !available,
      unavailableLabel: day.unavailableLabel(reason?.label ?? 'Tugadi'),
      onTap: widget.enabled ? () => widget.onTap(p) : null,
      onLongPress: widget.enabled ? () => widget.onLongPress(p) : null,
    );
    // P6-32: the sizes on the tile itself (0,3 / 0,5 / 0,7): one tap adds the dish in that size
    final PosModifierGroup? size = widget.onQuickPick == null || p.isCombo
        ? null
        : sizeGroupOf(p);
    if (size == null || !available) return tile;
    final List<PosModifier> sizes = <PosModifier>[
      for (final PosModifier m in size.modifiers)
        if (m.isAvailable && !cat.availability.stoppedModifiers.contains(m.id))
          m,
    ];
    if (sizes.isEmpty) return tile;
    final Set<String> defaults = <String>{
      for (final PosModifier m in preselectedOf(
        size,
        availability: cat.availability,
      ))
        m.uuid,
    };
    final Widget chips = Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        for (final PosModifier m in sizes)
          Padding(
            padding: const EdgeInsets.only(right: 4),
            child: QuickChip(
              key: ValueKey<String>('menu-${p.id}-size-${m.uuid}'),
              label: m.name,
              selected: defaults.contains(m.uuid),
              square: true,
              compact: true,
              onTap: widget.enabled ? () => widget.onQuickPick!(p, m) : null,
            ),
          ),
      ],
    );
    final bool phone = widget.columns != null;
    return Stack(
      children: <Widget>[
        Positioned.fill(child: tile),
        if (phone)
          Positioned(right: 8, top: 0, bottom: 0, child: Center(child: chips))
        else
          Positioned(
            left: 12,
            right: 6,
            bottom: 8,
            child: FittedBox(
              alignment: Alignment.centerLeft,
              fit: BoxFit.scaleDown,
              child: chips,
            ),
          ),
      ],
    );
  }

  String? _comboDetail(PosProduct p) {
    final List<String> parts = <String>[
      for (final PosComboSlot s in p.combo!.slots)
        if (s.isRequired) ?s.defaultOption?.name,
    ];
    return parts.isEmpty ? 'Kombo' : parts.join(' · ');
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosCatalog cat = widget.catalog;
    final String query = _search.text.trim();
    final PosModules modules = menuModulesOf(context);
    // P6-15: the dishes off today's menu are hidden (a search still finds them, greyed)
    final List<PosProduct> products = visibleProducts(
      query.isNotEmpty
          ? cat.search(query)
          : (_category == null ? cat.products : cat.productsIn(_category!)),
      cat,
      modules,
      searching: query.isNotEmpty,
    );
    final List<PosProduct> frequent = visibleProducts(
      widget.frequent,
      cat,
      modules,
      searching: false,
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
          child: TextField(
            controller: _search,
            decoration: InputDecoration(
              hintText: 'Taom qidirish',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: query.isEmpty
                  ? null
                  : IconButton(
                      tooltip: 'Tozalash',
                      onPressed: _search.clear,
                      icon: const Icon(Icons.close),
                    ),
              border: const OutlineInputBorder(),
              isDense: true,
            ),
          ),
        ),
        const DailyMenuMissingBanner(compact: true),
        if (frequent.isNotEmpty && query.isEmpty)
          SizedBox(
            height: 64,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: <Widget>[
                Center(
                  child: Text(
                    'Tez-tez:',
                    style: RzType.footnote.copyWith(color: c.fgMuted),
                  ),
                ),
                const SizedBox(width: 8),
                for (final PosProduct p in frequent)
                  Padding(
                    padding: const EdgeInsets.only(right: 8, bottom: 6),
                    child: _FrequentButton(
                      key: ValueKey<String>('frequent-${p.id}'),
                      product: p,
                      price: _subtitle(p),
                      available: cat.isProductAvailable(p),
                      onTap: widget.enabled ? () => widget.onTap(p) : null,
                    ),
                  ),
              ],
            ),
          ),
        if (query.isEmpty)
          SizedBox(
            height: 60,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              children: <Widget>[
                _CategoryChip(
                  label: 'Hammasi',
                  selected: _category == null,
                  onTap: () => setState(() => _category = null),
                ),
                for (final PosCategory k in cat.categories)
                  _CategoryChip(
                    label: k.name,
                    color: _accentOf(k.id),
                    selected: _category == k.id,
                    onTap: () => setState(() => _category = k.id),
                  ),
              ],
            ),
          ),
        Expanded(
          child: products.isEmpty
              ? EmptyState(
                  icon: Icons.search_off,
                  title: query.isEmpty ? "Taom yo'q" : 'Topilmadi',
                  message: query.isEmpty
                      ? "Bu kategoriyada taom yo'q."
                      : '«$query» bo\'yicha taom topilmadi.',
                )
              : GridView.builder(
                  padding: const EdgeInsets.fromLTRB(12, 4, 12, 12),
                  gridDelegate: widget.columns == null
                      ? const SliverGridDelegateWithMaxCrossAxisExtent(
                          maxCrossAxisExtent: 190,
                          mainAxisExtent: 132,
                          crossAxisSpacing: 10,
                          mainAxisSpacing: 10,
                        )
                      : SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: widget.columns!,
                          mainAxisExtent: 96,
                          crossAxisSpacing: 10,
                          mainAxisSpacing: 10,
                        ),
                  itemCount: products.length,
                  itemBuilder: (BuildContext context, int i) =>
                      _tile(products[i]),
                ),
        ),
      ],
    );
  }
}

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({
    required this.label,
    required this.selected,
    required this.onTap,
    this.color,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(right: 8),
    child: ChoiceChip(
      label: Text(label),
      avatar: color == null
          ? null
          : CircleAvatar(backgroundColor: color, radius: 6),
      selected: selected,
      showCheckmark: false,
      materialTapTargetSize: MaterialTapTargetSize.padded,
      labelStyle: RzType.headline,
      onSelected: (_) => onTap(),
    ),
  );
}

/// "Tez-tez" panelidagi ixcham tugma: nom va narx bir qatordan.
class _FrequentButton extends StatelessWidget {
  const _FrequentButton({
    super.key,
    required this.product,
    required this.price,
    required this.available,
    required this.onTap,
  });

  final PosProduct product;
  final String price;
  final bool available;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Semantics(
      button: true,
      enabled: available && onTap != null,
      label: '${product.name}, $price',
      excludeSemantics: true,
      child: OutlinedButton(
        onPressed: available ? onTap : null,
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(96, 52),
          padding: const EdgeInsets.symmetric(horizontal: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(RzRadius.card),
          ),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              product.name,
              maxLines: 1,
              style: RzType.headline.copyWith(
                color: available ? c.fg : c.fgSubtle,
              ),
            ),
            Text(
              price,
              maxLines: 1,
              style: RzType.caption.copyWith(color: c.fgMuted),
            ),
          ],
        ),
      ),
    );
  }
}
