import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// "Mening xonalarim": ofitsiantning xonalari va stollari, bosqich qo'shish, yuborish, schyot so'rash (P3-25).
///
/// **Kelishuv:** ish ekrani shu vidjetni ochadi (`const RoomsPage()`); bog'liqliklar — `WaiterScope.of(context)`.
class RoomsPage extends StatelessWidget {
  const RoomsPage({super.key});

  @override
  Widget build(BuildContext context) => ColoredBox(
    color: context.rz.page,
    child: const EmptyState(
      icon: Icons.meeting_room_outlined,
      title: 'Mening xonalarim',
      message: "Sizga biriktirilgan xona va stollar shu yerda bo'ladi.",
    ),
  );
}
