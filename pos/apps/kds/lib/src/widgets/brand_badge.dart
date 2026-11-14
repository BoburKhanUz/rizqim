import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// P6-55 (`multi_brand`): brendning qisqa kodi brend rangida ("BB"). Yozuv rangi fon yorqinligiga qarab oq yoki qora
/// (o'qilishi uchun). Brendsiz chiptada ko'rsatilmaydi.
class BrandBadge extends StatelessWidget {
  const BrandBadge({super.key, required this.brand, this.large = false});

  final KitchenBrand brand;

  /// Chipta sarlavhasida kattaroq.
  final bool large;

  /// Fon ustida o'qiladigan yozuv rangi.
  static Color textOn(Color bg) =>
      bg.computeLuminance() > 0.4 ? const Color(0xFF111111) : Colors.white;

  /// Belgidagi matn: kod katta harflarda, kod bo'sh bo'lsa nomning bosh harflari.
  static String textOf(KitchenBrand b) {
    final String code = b.code.trim();
    if (code.isNotEmpty) return code.toUpperCase();
    final String name = b.name.trim();
    return name.isEmpty ? '?' : name.substring(0, 1).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final Color bg = Color(brand.argb);
    return Semantics(
      label: 'Brend ${brand.name.isEmpty ? textOf(brand) : brand.name}',
      excludeSemantics: true,
      child: Container(
        key: ValueKey<String>('brand-${brand.id}'),
        padding: EdgeInsets.symmetric(
          horizontal: large ? 8 : 6,
          vertical: large ? 2 : 1,
        ),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(6),
        ),
        child: Text(
          textOf(brand),
          maxLines: 1,
          style: TextStyle(
            color: textOn(bg),
            fontSize: large ? 16 : 14,
            height: 1.2,
            fontWeight: FontWeight.w800,
            letterSpacing: 0.6,
          ),
        ),
      ),
    );
  }
}

/// Qator sarlavhasi: brendli qatorda kod belgisi nomdan oldin; brendsiz qatorda oddiy [Text].
class BrandedTitle extends StatelessWidget {
  const BrandedTitle({
    super.key,
    required this.title,
    required this.brand,
    required this.style,
    this.maxLines,
    this.overflow,
  });

  final String title;
  final KitchenBrand? brand;
  final TextStyle style;
  final int? maxLines;
  final TextOverflow? overflow;

  @override
  Widget build(BuildContext context) {
    final KitchenBrand? b = brand;
    if (b == null) {
      return Text(title, style: style, maxLines: maxLines, overflow: overflow);
    }
    return Text.rich(
      TextSpan(
        children: <InlineSpan>[
          WidgetSpan(
            alignment: PlaceholderAlignment.middle,
            child: Padding(
              padding: const EdgeInsets.only(right: 6),
              child: BrandBadge(brand: b),
            ),
          ),
          TextSpan(text: title),
        ],
      ),
      style: style,
      maxLines: maxLines,
      overflow: overflow,
    );
  }
}
