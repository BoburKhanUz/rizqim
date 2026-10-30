import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Buyurtma kurslari (P6-21): serverning `courses[]`; u bo'sh, lekin qatorlarda kurs bo'lsa (eski javob) — qatorlardan
/// hisoblanadi (o'chirilgan qatorlarsiz).
List<PosCourse> coursesOf(PosOrder order) {
  if (order.courses.isNotEmpty) {
    return <PosCourse>[...order.courses]
      ..sort((PosCourse a, PosCourse b) => a.courseNo.compareTo(b.courseNo));
  }
  final Map<int, List<PosOrderItem>> byCourse = <int, List<PosOrderItem>>{};
  for (final PosOrderItem i in order.items) {
    final int? n = i.courseNo;
    if (n == null || i.isVoid) continue;
    (byCourse[n] ??= <PosOrderItem>[]).add(i);
  }
  final List<int> numbers = byCourse.keys.toList()..sort();
  return <PosCourse>[for (final int n in numbers) _derive(n, byCourse[n]!)];
}

PosCourse _derive(int n, List<PosOrderItem> items) {
  final int sent = items.where((PosOrderItem i) => i.wasSent).length;
  final int served = items
      .where((PosOrderItem i) => i.kitchenStatus == PosKitchenStatus.served)
      .length;
  final int ready = items
      .where((PosOrderItem i) => i.kitchenStatus == PosKitchenStatus.ready)
      .length;
  final PosCourseStatus status;
  if (sent == 0) {
    status = PosCourseStatus.held;
  } else if (served == items.length) {
    status = PosCourseStatus.served;
  } else if (ready + served >= sent) {
    status = PosCourseStatus.ready;
  } else {
    status = PosCourseStatus.sent;
  }
  return PosCourse(
    courseNo: n,
    status: status,
    items: items.length,
    sent: sent,
    ready: ready,
    served: served,
  );
}

/// Kurs holati ofitsiant tilida: kutmoqda, yuborildi · tayyorlanmoqda, tayyor, berildi.
String courseStateLabel(PosCourse c) => switch (c.status) {
  PosCourseStatus.held => 'Kutmoqda',
  PosCourseStatus.sent =>
    c.ready > 0
        ? 'Tayyorlanmoqda: ${c.ready + c.served}/${c.sent} tayyor'
        : 'Yuborildi · tayyorlanmoqda',
  PosCourseStatus.ready => 'Tayyor',
  PosCourseStatus.served => 'Berildi',
};

/// "Kurslar" bloki buyurtma ekranida: har kurs holati bilan, yuboriladiganida "N-kursni yuborish".
class CoursesCard extends StatelessWidget {
  const CoursesCard({
    super.key,
    required this.courses,
    this.editable = false,
    this.enabled = true,
    this.onSend,
  });

  final List<PosCourse> courses;

  /// Yuborish tugmalari ko'rinadi (o'z buyurtmasi yoki tahrirlash yoqilgan).
  final bool editable;
  final bool enabled;
  final ValueChanged<int>? onSend;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final int? next = courses
        .where((PosCourse x) => x.canSend)
        .map((PosCourse x) => x.courseNo)
        .firstOrNull;
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                Icon(Icons.layers_outlined, size: 20, color: c.fgMuted),
                const SizedBox(width: 8),
                Text('Kurslar', style: RzType.headline.copyWith(color: c.fg)),
              ],
            ),
            const SizedBox(height: 6),
            for (final PosCourse k in courses)
              _CourseRow(
                course: k,
                primary: k.courseNo == next,
                showSend: editable && k.canSend && onSend != null,
                enabled: enabled,
                onSend: () => onSend?.call(k.courseNo),
              ),
          ],
        ),
      ),
    );
  }
}

class _CourseRow extends StatelessWidget {
  const _CourseRow({
    required this.course,
    required this.primary,
    required this.showSend,
    required this.enabled,
    required this.onSend,
  });

  final PosCourse course;
  final bool primary;
  final bool showSend;
  final bool enabled;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final (IconData icon, Color bg, Color fg) = switch (course.status) {
      PosCourseStatus.held => (
        Icons.pause_circle_outline,
        c.infoSoft,
        c.infoSoftFg,
      ),
      PosCourseStatus.sent => (
        Icons.soup_kitchen_outlined,
        c.warningSoft,
        c.warningSoftFg,
      ),
      PosCourseStatus.ready => (
        Icons.room_service_outlined,
        c.successSoft,
        c.successSoftFg,
      ),
      PosCourseStatus.served => (
        Icons.check_circle_outline,
        c.surface2,
        c.fgMuted,
      ),
    };
    final String state = courseStateLabel(course);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Semantics(
            label: '${course.label}: $state, ${course.items} ta taom',
            excludeSemantics: true,
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 40),
              child: Row(
                children: <Widget>[
                  SizedBox(
                    width: 64,
                    child: Text(
                      course.label,
                      style: RzType.headline.copyWith(color: c.fg),
                    ),
                  ),
                  Expanded(
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          color: bg,
                          borderRadius: BorderRadius.circular(RzRadius.control),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: <Widget>[
                              Icon(icon, size: 16, color: fg),
                              const SizedBox(width: 6),
                              Flexible(
                                child: Text(
                                  state,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: RzType.caption.copyWith(color: fg),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    '${course.items} ta',
                    style: RzType.body.copyWith(color: c.fgMuted),
                  ),
                ],
              ),
            ),
          ),
          if (showSend) ...<Widget>[
            const SizedBox(height: 6),
            RzButton(
              label: '${course.courseNo}-kursni yuborish',
              icon: Icons.send_rounded,
              variant: primary
                  ? RzButtonVariant.success
                  : RzButtonVariant.secondary,
              size: RzButtonSize.lg,
              fullWidth: true,
              onPressed: enabled ? onSend : null,
            ),
          ],
        ],
      ),
    );
  }
}
