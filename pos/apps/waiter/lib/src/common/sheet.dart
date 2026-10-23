import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../shell/waiter_scope.dart';

/// Pastdan chiqadigan oyna: [WaiterScope] ni saqlaydi (modal route alohida daraxtda yashaydi), klaviatura chiqsa
/// ko'tariladi. [fullHeight] — ekranning ko'p qismi (menyu tanlash).
Future<T?> showWaiterSheet<T>(
  BuildContext context, {
  required Widget Function(BuildContext context) builder,
  bool fullHeight = false,
}) {
  final WaiterScope scope = WaiterScope.of(context);
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    constraints: fullHeight
        ? BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.94)
        : null,
    builder: (BuildContext sheetContext) => WaiterScope(
      services: scope.services,
      realtime: scope.realtime,
      child: Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(sheetContext).bottom,
        ),
        child: builder(sheetContext),
      ),
    ),
  );
}

/// Oyna sarlavhasi (sarlavha + ixtiyoriy izoh).
class SheetHeader extends StatelessWidget {
  const SheetHeader({super.key, required this.title, this.subtitle});

  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(title, style: RzType.title.copyWith(color: c.fg)),
        if (subtitle != null) ...<Widget>[
          const SizedBox(height: 2),
          Text(subtitle!, style: RzType.body.copyWith(color: c.fgMuted)),
        ],
      ],
    );
  }
}
