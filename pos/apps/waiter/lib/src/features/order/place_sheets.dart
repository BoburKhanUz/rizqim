import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../common/guests_stepper.dart';
import '../../common/sheet.dart';

/// Mehmonlar sonini o'zgartirish. Yangi son bilan yopiladi (choy-non qatorlarini server qo'shadi).
Future<int?> showGuestsSheet(BuildContext context, {required int current}) =>
    showWaiterSheet<int>(
      context,
      builder: (BuildContext context) => _GuestsSheet(current: current),
    );

class _GuestsSheet extends StatefulWidget {
  const _GuestsSheet({required this.current});

  final int current;

  @override
  State<_GuestsSheet> createState() => _GuestsSheetState();
}

class _GuestsSheetState extends State<_GuestsSheet> {
  late int _guests = widget.current < 1 ? 1 : widget.current;

  @override
  Widget build(BuildContext context) => SafeArea(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const SheetHeader(
            title: 'Mehmonlar soni',
            subtitle: "Ko'paysa, choy va non qatorlari o'zi qo'shiladi.",
          ),
          const SizedBox(height: 20),
          GuestsStepper(
            value: _guests,
            onChanged: (int v) => setState(() => _guests = v),
          ),
          const SizedBox(height: 20),
          RzButton(
            label: 'Saqlash',
            size: RzButtonSize.lg,
            fullWidth: true,
            onPressed: _guests == widget.current
                ? null
                : () => Navigator.of(context).pop(_guests),
          ),
        ],
      ),
    ),
  );
}

/// Buyurtmani ko'chirish: bo'sh joylar (zallar bo'yicha). Tanlangan joy id'si bilan yopiladi.
Future<int?> showMoveSheet(
  BuildContext context, {
  required Future<PosFloor> Function() loadFloor,
  required int? currentTableId,
}) => showWaiterSheet<int>(
  context,
  fullHeight: true,
  builder: (BuildContext context) =>
      _MoveSheet(loadFloor: loadFloor, currentTableId: currentTableId),
);

class _MoveSheet extends StatefulWidget {
  const _MoveSheet({required this.loadFloor, required this.currentTableId});

  final Future<PosFloor> Function() loadFloor;
  final int? currentTableId;

  @override
  State<_MoveSheet> createState() => _MoveSheetState();
}

class _MoveSheetState extends State<_MoveSheet> {
  late Future<PosFloor> _floor = widget.loadFloor();

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return SafeArea(
      child: Column(
        children: <Widget>[
          const Padding(
            padding: EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Align(
              alignment: Alignment.centerLeft,
              child: SheetHeader(
                title: "Boshqa joyga ko'chirish",
                subtitle: "Faqat bo'sh joylar ko'rsatiladi.",
              ),
            ),
          ),
          Expanded(
            child: FutureBuilder<PosFloor>(
              future: _floor,
              builder: (BuildContext context, AsyncSnapshot<PosFloor> snap) {
                if (snap.hasError) {
                  return Padding(
                    padding: const EdgeInsets.all(16),
                    child: InlineError(
                      message: "Joylar ro'yxatini yuklab bo'lmadi.",
                      onRetry: () =>
                          setState(() => _floor = widget.loadFloor()),
                    ),
                  );
                }
                final PosFloor? floor = snap.data;
                if (floor == null) {
                  return const Center(child: CircularProgressIndicator());
                }
                final List<Widget> rows = <Widget>[];
                for (final FloorHall h in floor.halls) {
                  final List<FloorTable> free = <FloorTable>[
                    for (final FloorTable t in h.tables)
                      if (t.isActive &&
                          h.isActive &&
                          t.id != widget.currentTableId &&
                          t.isFree)
                        t,
                  ];
                  if (free.isEmpty) continue;
                  rows.add(
                    Padding(
                      padding: const EdgeInsets.fromLTRB(4, 12, 4, 6),
                      child: Text(
                        h.name,
                        style: RzType.headline.copyWith(color: c.fgMuted),
                      ),
                    ),
                  );
                  for (final FloorTable t in free) {
                    rows.add(
                      Padding(
                        padding: const EdgeInsets.only(bottom: 6),
                        child: Material(
                          color: Color.alphaBlend(c.surface2, c.surface),
                          borderRadius: BorderRadius.circular(RzRadius.control),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(
                              RzRadius.control,
                            ),
                            onTap: () => Navigator.of(context).pop(t.id),
                            child: ConstrainedBox(
                              constraints: const BoxConstraints(minHeight: 56),
                              child: Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 14,
                                ),
                                child: Row(
                                  children: <Widget>[
                                    Icon(
                                      t.kind == FloorTableKind.room
                                          ? Icons.meeting_room_outlined
                                          : Icons.table_restaurant_outlined,
                                      color: c.fg,
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Text(
                                        t.name,
                                        style: RzType.headline.copyWith(
                                          color: c.fg,
                                        ),
                                      ),
                                    ),
                                    if (t.seats != null)
                                      Text(
                                        "${t.seats} o'rin",
                                        style: RzType.body.copyWith(
                                          color: c.fgMuted,
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    );
                  }
                }
                if (rows.isEmpty) {
                  return const EmptyState(
                    icon: Icons.event_busy_outlined,
                    title: "Bo'sh joy yo'q",
                  );
                }
                return ListView(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                  children: rows,
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
