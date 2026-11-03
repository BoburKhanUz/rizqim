import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// P6-31: bir komboning qismlari ro'yxatida sarlavha: "Kombo: Lavash kombo ×2". Kombo qatorining o'zi oshxonaga
/// chiqmaydi, har qismi o'z sexida pishadi; shu sarlavha qaysi komboga tegishli ekanini aytadi.
class ComboHeading extends StatelessWidget {
  const ComboHeading({super.key, required this.combo});

  final KitchenCombo combo;

  /// [lines] ro'yxatida [index] dagi qator yangi kombo qismlari guruhini boshlaydimi.
  static bool startsGroup(List<KitchenLine> lines, int index) {
    final KitchenCombo? combo = lines[index].combo;
    if (combo == null) return false;
    return index == 0 || lines[index - 1].combo?.itemId != combo.itemId;
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Padding(
      key: ValueKey<String>('combo-${combo.itemId}'),
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
      child: Semantics(
        label: 'Kombo: ${combo.label}',
        excludeSemantics: true,
        child: Row(
          children: <Widget>[
            Icon(Icons.lunch_dining_outlined, size: 18, color: c.brandSoftFg),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                'Kombo: ${combo.label}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: RzType.footnote.copyWith(
                  color: c.brandSoftFg,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.4,
                ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(child: Divider(height: 1, color: c.line)),
          ],
        ),
      ),
    );
  }
}
