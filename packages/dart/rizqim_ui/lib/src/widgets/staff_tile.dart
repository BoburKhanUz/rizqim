import 'package:flutter/material.dart';

import '../theme/rz_colors.dart';
import '../theme/rz_theme.dart';

/// PIN ekranidagi xodim: bosh harflar doirasi, ism va rol. Kamida 64px balandlikda.
class RzStaffTile extends StatelessWidget {
  const RzStaffTile({
    super.key,
    required this.name,
    required this.subtitle,
    required this.onTap,
    this.selected = false,
  });

  final String name;
  final String subtitle;
  final VoidCallback? onTap;
  final bool selected;

  /// "Aziz Karimov" -> "AK", "aziz" -> "A".
  static String initials(String name) {
    final List<String> parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((String p) => p.isNotEmpty)
        .toList();
    if (parts.isEmpty) return '?';
    final String first = parts.first.characters.first;
    final String second = parts.length > 1 ? parts[1].characters.first : '';
    return (first + second).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final Color bg = selected
        ? Color.alphaBlend(c.brandSoft, c.surface)
        : c.surface;
    return Semantics(
      button: true,
      selected: selected,
      label: '$name, $subtitle',
      excludeSemantics: true,
      child: Material(
        color: bg,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RzRadius.card),
          side: BorderSide(
            color: selected ? c.brand : c.line,
            width: selected ? 2 : 1,
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 72),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              child: Row(
                children: <Widget>[
                  CircleAvatar(
                    radius: 24,
                    backgroundColor: Color.alphaBlend(c.brandSoft, c.surface),
                    foregroundColor: c.brandSoftFg,
                    child: Text(initials(name), style: RzType.headline),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Text(
                          name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: RzType.headline.copyWith(color: c.fg),
                        ),
                        Text(
                          subtitle,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: RzType.footnote.copyWith(color: c.fgMuted),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
