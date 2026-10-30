import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../common/modules.dart';
import '../../common/sheet.dart';

/// Kurs raqamlari: 1–3 tez tugma, 4–9 "Yana" ichida (P6-21: backend 1–9 qabul qiladi).
const int maxCourse = 9;
const int quickCourses = 3;

/// "Kurs: Kurssiz · 1 · 2 · 3 · Yana": qatorning kursi (P6-21). `null` — kurssiz (darhol oshxonaga).
class CourseChips extends StatelessWidget {
  const CourseChips({super.key, required this.value, required this.onChanged});

  final int? value;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final int? v = value;
    return _ChipRow(
      label: 'Kurs',
      children: <Widget>[
        _chip(context, 'Kurssiz', v == null, () => onChanged(null)),
        for (var n = 1; n <= quickCourses; n++)
          _chip(context, '$n-kurs', v == n, () => onChanged(n)),
        if (v != null && v > quickCourses)
          _chip(context, '$v-kurs', true, () => onChanged(v)),
        PopupMenuButton<int>(
          tooltip: 'Boshqa kurs',
          onSelected: onChanged,
          itemBuilder: (BuildContext context) => <PopupMenuEntry<int>>[
            for (var n = quickCourses + 1; n <= maxCourse; n++)
              PopupMenuItem<int>(value: n, height: 48, child: Text('$n-kurs')),
          ],
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: Chip(
              avatar: Icon(Icons.more_horiz, size: 18, color: c.fgMuted),
              label: const Text('Yana'),
              materialTapTargetSize: MaterialTapTargetSize.padded,
            ),
          ),
        ),
      ],
    );
  }
}

/// "Mehmon: Umumiy · 1 · 2 · …": qator qaysi mehmonniki (P6-24, mehmon bo'yicha bo'lish uchun). `null` — umumiy.
class SeatChips extends StatelessWidget {
  const SeatChips({
    super.key,
    required this.guests,
    required this.value,
    required this.onChanged,
  });

  /// Stol mehmonlari soni (tugmalar 1..N).
  final int? guests;
  final int? value;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) {
    final int? v = value;
    final List<int> seats = seatNumbers(guests);
    return _ChipRow(
      label: 'Mehmon',
      children: <Widget>[
        _chip(context, 'Umumiy', v == null, () => onChanged(null)),
        for (final int n in seats)
          _chip(
            context,
            '$n',
            v == n,
            () => onChanged(n),
            semantics: '$n-mehmon',
          ),
        if (v != null && !seats.contains(v))
          _chip(
            context,
            '$v',
            true,
            () => onChanged(v),
            semantics: '$v-mehmon',
          ),
      ],
    );
  }
}

Widget _chip(
  BuildContext context,
  String label,
  bool selected,
  VoidCallback onTap, {
  String? semantics,
}) => Padding(
  padding: const EdgeInsets.symmetric(horizontal: 4),
  child: Semantics(
    label: semantics,
    child: ChoiceChip(
      label: Text(label),
      selected: selected,
      materialTapTargetSize: MaterialTapTargetSize.padded,
      visualDensity: VisualDensity.comfortable,
      labelStyle: RzType.body,
      onSelected: (_) => onTap(),
    ),
  ),
);

class _ChipRow extends StatelessWidget {
  const _ChipRow({required this.label, required this.children});

  final String label;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    // A few chips: all built at once (scrolls sideways on a narrow phone)
    return SizedBox(
      height: 52,
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        child: Row(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.only(left: 4, right: 4),
              child: Text(
                label,
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ),
            ...children,
          ],
        ),
      ),
    );
  }
}

/// Qatorning kursi va/yoki mehmonini tanlash oynasi (savatda yoki buyurtmadagi qatorda). Natija — `(kurs, mehmon)`;
/// yopilsa `null`.
Future<({int? course, int? seat})?> showSlotSheet(
  BuildContext context, {
  required String title,
  required bool course,
  required bool seat,
  int? guests,
  int? initialCourse,
  int? initialSeat,
}) => showWaiterSheet<({int? course, int? seat})>(
  context,
  builder: (BuildContext context) => _SlotSheet(
    title: title,
    course: course,
    seat: seat,
    guests: guests,
    initialCourse: initialCourse,
    initialSeat: initialSeat,
  ),
);

class _SlotSheet extends StatefulWidget {
  const _SlotSheet({
    required this.title,
    required this.course,
    required this.seat,
    this.guests,
    this.initialCourse,
    this.initialSeat,
  });

  final String title;
  final bool course;
  final bool seat;
  final int? guests;
  final int? initialCourse;
  final int? initialSeat;

  @override
  State<_SlotSheet> createState() => _SlotSheetState();
}

class _SlotSheetState extends State<_SlotSheet> {
  late int? _course = widget.initialCourse;
  late int? _seat = widget.initialSeat;

  @override
  Widget build(BuildContext context) => SafeArea(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(0, 0, 0, 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: SheetHeader(title: widget.title),
          ),
          const SizedBox(height: 8),
          if (widget.course)
            CourseChips(
              value: _course,
              onChanged: (int? v) => setState(() => _course = v),
            ),
          if (widget.seat)
            SeatChips(
              guests: widget.guests,
              value: _seat,
              onChanged: (int? v) => setState(() => _seat = v),
            ),
          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: RzButton(
              label: 'Saqlash',
              size: RzButtonSize.lg,
              fullWidth: true,
              onPressed:
                  _course == widget.initialCourse && _seat == widget.initialSeat
                  ? null
                  : () =>
                        Navigator.of(context)
                            .pop((course: _course, seat: _seat)),
            ),
          ),
        ],
      ),
    ),
  );
}
