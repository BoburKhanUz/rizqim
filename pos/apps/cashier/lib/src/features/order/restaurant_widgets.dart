import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'order_format.dart';

/// P6-21 / P6-25 / P6-24: kassa buyurtma ko'rinishidagi restoran qismlari — og'irlik oynasi, qatorga mehmon raqami,
/// kurslar paneli va qator belgisi (kurs, og'irlik kutilmoqda).

/// Qator holati belgisi: og'irlik kutilayotgan qator — "Og'irlik kutilmoqda"; kursli qator — "2-kurs · Oshxonada".
PosToneChip restaurantStatusChip(PosOrderItem i) {
  if (i.awaitingWeight) {
    return PosToneChip(
      label: i.courseNo == null
          ? PosWeightStatus.awaiting.label
          : '${i.courseNo}-kurs · ${PosWeightStatus.awaiting.label}',
      tone: PosChipTone.warning,
      icon: Icons.scale_outlined,
    );
  }
  final PosToneChip base = kitchenStatusChip(i);
  if (i.courseNo == null) return base;
  return PosToneChip(
    label: '${i.courseNo}-kurs · ${base.label}',
    tone: base.tone,
    icon: base.icon,
  );
}

/// "Og'irlik kutilmoqda (2)" — schyot tugmasi o'rniga.
String weightPendingLabel(int count) => "Og'irlik kutilmoqda ($count)";

/// Og'irlikni kiritish (P6-25): kg (l) klaviaturasi, xom yoki pishgan og'irlik yorlig'i, kg narxi. Tarozi ulanishi hali
/// yo'q: og'irlik qo'lda teriladi. Tanlangan miqdor yoki `null`.
Future<PosQuantity?> showWeightDialog(
  BuildContext context, {
  required PosOrderItem item,
}) => showDialog<PosQuantity>(
  context: context,
  builder: (BuildContext context) {
    final RzColors c = context.rz;
    final PosWeightBasis? basis = item.weightBasis;
    final bool again = item.weightStatus == PosWeightStatus.weighed;
    return Dialog(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 400),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 8, 8, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      item.displayName,
                      style: RzType.title.copyWith(color: c.fg),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Yopish',
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              Padding(
                padding: const EdgeInsets.only(right: 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Text(
                      basis == null
                          ? "Og'irlik (${item.unit.label})"
                          : '${basis.label[0].toUpperCase()}${basis.label.substring(1)} '
                                "og'irlik (${item.unit.label})",
                      key: const ValueKey<String>('weight-basis'),
                      style: RzType.headline.copyWith(color: c.fg),
                    ),
                    Text(
                      '${item.unitPrice.format()} / ${item.unit.label}'
                      '${again ? '' : ' · taxminan ${item.quantity.label(item.unit)}'}',
                      style: RzType.body.copyWith(color: c.fgMuted),
                    ),
                    if (again)
                      Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Text(
                          "Taom tortilgan (${item.quantity.label(item.unit)}): qayta tortish menejer tasdig'i bilan.",
                          style: RzType.footnote.copyWith(
                            color: c.warningSoftFg,
                          ),
                        ),
                      ),
                    const SizedBox(height: 10),
                    PosQuantityKeypad(
                      key: const ValueKey<String>('weight-keypad'),
                      unit: item.unit.isMeasured ? item.unit : PosUnit.kg,
                      max: const PosQuantity.whole(999),
                      submitLabel: "Og'irlikni yozish",
                      keySize: 60,
                      autofocus: true,
                      onSubmit: (PosQuantity q) => Navigator.of(context).pop(q),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  },
);

/// Qatorga mehmon raqami (P6-24): 1…N (stol mehmonlari, kamida hozirgi raqam) yoki "Umumiy". Tanlov: `(raqam)`
/// (`null` — umumiy) yoki bekor qilinsa `null`.
Future<(int?,)?> showSeatPicker(
  BuildContext context, {
  required PosOrderItem item,
  int? guests,
}) {
  final int count = <int>[
    guests ?? 0,
    item.seatNo ?? 0,
    2,
  ].reduce((int a, int b) => a > b ? a : b).clamp(2, 30);
  return showModalBottomSheet<(int?,)>(
    context: context,
    showDragHandle: true,
    builder: (BuildContext context) {
      final RzColors c = context.rz;
      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                '${item.displayName}: qaysi mehmon?',
                style: RzType.title.copyWith(color: c.fg),
              ),
              const SizedBox(height: 4),
              Text(
                "Mehmon bo'yicha bo'lishda qator shu mehmonning schyotiga tushadi; umumiy qator hammaga teng bo'linadi.",
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  for (var n = 1; n <= count; n++)
                    ChoiceChip(
                      key: ValueKey<String>('seat-$n'),
                      label: Text('$n-mehmon'),
                      selected: item.seatNo == n,
                      materialTapTargetSize: MaterialTapTargetSize.padded,
                      onSelected: (_) => Navigator.of(context).pop((n,)),
                    ),
                  ChoiceChip(
                    key: const ValueKey<String>('seat-none'),
                    label: const Text('Umumiy'),
                    selected: item.seatNo == null,
                    materialTapTargetSize: MaterialTapTargetSize.padded,
                    onSelected: (_) => Navigator.of(context).pop((null,)),
                  ),
                ],
              ),
            ],
          ),
        ),
      );
    },
  );
}

/// Kurslar paneli (P6-21): har kurs holati ("Kutmoqda", "Oshxonada", "Tayyor", "Berildi") va kutayotgan kursni
/// yuborish tugmasi ("2-kursni yuborish").
class CourseBar extends StatelessWidget {
  const CourseBar({
    super.key,
    required this.courses,
    required this.onSend,
    this.enabled = true,
  });

  final List<PosCourse> courses;
  final ValueChanged<int> onSend;
  final bool enabled;

  static PosChipTone _tone(PosCourseStatus s) => switch (s) {
    PosCourseStatus.held => PosChipTone.warning,
    PosCourseStatus.sent => PosChipTone.info,
    PosCourseStatus.ready => PosChipTone.success,
    PosCourseStatus.served => PosChipTone.neutral,
  };

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    // The next course to fire: the lowest one still holding dishes
    final PosCourse? next = courses
        .where((PosCourse k) => k.canSend)
        .fold<PosCourse?>(
          null,
          (PosCourse? a, PosCourse b) =>
              a == null || b.courseNo < a.courseNo ? b : a,
        );
    return Material(
      color: Color.alphaBlend(c.surface2, c.surface),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 6, 8, 6),
        child: Row(
          children: <Widget>[
            Expanded(
              child: Wrap(
                spacing: 6,
                runSpacing: 4,
                children: <Widget>[
                  for (final PosCourse k in courses)
                    PosToneChip(
                      key: ValueKey<String>('course-${k.courseNo}'),
                      label: '${k.label}: ${k.status.label}',
                      tone: _tone(k.status),
                    ),
                ],
              ),
            ),
            if (next != null)
              TextButton.icon(
                key: ValueKey<String>('send-course-${next.courseNo}'),
                onPressed: enabled ? () => onSend(next.courseNo) : null,
                icon: const Icon(Icons.send_outlined),
                label: Text('${next.courseNo}-kursni yuborish'),
              ),
          ],
        ),
      ),
    );
  }
}
