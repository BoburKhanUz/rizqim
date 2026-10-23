import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'order_format.dart';

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
  });

  final PosCatalog catalog;
  final ValueChanged<PosProduct> onTap;

  /// Uzoq bosish: qatorni to'liq sozlab qo'shish (qo'shimcha, izoh, o'rin).
  final ValueChanged<PosProduct> onLongPress;
  final List<PosProduct> frequent;
  final bool enabled;

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
    final bool available = cat.isProductAvailable(p);
    PosUnavailableReason? reason;
    if (!available) {
      reason = cat.availability.stoppedProducts.contains(p.id)
          ? PosUnavailableReason.stopped
          : (p.variants.isEmpty
                ? null
                : cat.unavailableReasonOf(p.variants.first));
    }
    return PosTileButton(
      key: ValueKey<String>('menu-${p.id}'),
      label: p.name,
      subtitle: _subtitle(p),
      badge: _badge(p),
      accent: _accentOf(p.categoryId),
      unavailable: !available,
      unavailableLabel: reason?.label ?? 'Tugadi',
      onTap: widget.enabled ? () => widget.onTap(p) : null,
      onLongPress: widget.enabled ? () => widget.onLongPress(p) : null,
    );
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosCatalog cat = widget.catalog;
    final String query = _search.text.trim();
    final List<PosProduct> products = query.isNotEmpty
        ? cat.search(query)
        : (_category == null ? cat.products : cat.productsIn(_category!));
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
        if (widget.frequent.isNotEmpty && query.isEmpty)
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
                for (final PosProduct p in widget.frequent)
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
                  gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                    maxCrossAxisExtent: 190,
                    mainAxisExtent: 132,
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
