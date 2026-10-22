import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/destinations.dart';
import '../../shell/pos_scope.dart';
import 'order_dialogs.dart';
import 'order_format.dart';

/// Joyda yangi buyurtma: qaysi joy, nechta o'rin, yaqin bron.
class OpenTarget {
  const OpenTarget({
    required this.tableId,
    required this.name,
    this.kind = FloorTableKind.table,
    this.seats,
    this.reservation,
  });

  factory OpenTarget.fromFloor(FloorTable t) => OpenTarget(
    tableId: t.id,
    name: t.name,
    kind: t.kind,
    seats: t.seats,
    reservation: t.reservation,
  );

  final int tableId;
  final String name;
  final FloorTableKind kind;
  final int? seats;
  final FloorReservation? reservation;
}

class OpenOrderRequest {
  const OpenOrderRequest({required this.guests, this.waiterId});

  final int guests;
  final int? waiterId;
}

/// "Buyurtma ochish" oynasi: mehmonlar soni, ofitsiant (ixtiyoriy). Bron kutilayotgan joyda — ogohlantirish va
/// "Bronlar" bo'limiga o'tish (bron bo'yicha kelganlar "Keldi" bilan ochiladi: avans va oldindan buyurtma bog'lanadi).
Future<OpenOrderRequest?> showOpenOrderDialog(
  BuildContext context, {
  required OpenTarget target,
  required List<PosStaffRef> waiters,
  void Function()? onOpenBookings,
}) => showDialog<OpenOrderRequest>(
  context: context,
  builder: (BuildContext context) {
    var guests = target.reservation?.guests ?? 2;
    int? waiterId;
    return StatefulBuilder(
      builder: (BuildContext context, StateSetter setState) {
        final RzColors c = context.rz;
        final FloorReservation? r = target.reservation;
        return AlertDialog(
          title: Text('${target.name}: yangi buyurtma'),
          content: SizedBox(
            width: 460,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  if (r != null) ...<Widget>[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Color.alphaBlend(c.warningSoft, c.surface),
                        borderRadius: BorderRadius.circular(RzRadius.card),
                      ),
                      child: Row(
                        children: <Widget>[
                          Icon(Icons.event, color: c.warningSoftFg),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'Bron kutilmoqda'
                              '${r.startsAt == null ? '' : ': ${formatReceiptTime(r.startsAt!.toLocal())}'}'
                              '${r.guests == null ? '' : ', ${r.guests} kishi'}'
                              '${r.contactName == null ? '' : ', ${r.contactName}'}'
                              '${r.contactPhone == null ? '' : ' (${r.contactPhone})'}. '
                              "Bron bo'yicha kelgan bo'lsa, Bronlar bo'limida «Keldi»ni bosing.",
                              style: RzType.body.copyWith(color: c.fg),
                            ),
                          ),
                          if (onOpenBookings != null)
                            TextButton(
                              onPressed: () {
                                Navigator.of(context).pop();
                                onOpenBookings();
                              },
                              child: const Text('Bronlar'),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                  Text(
                    'Mehmonlar soni',
                    style: RzType.headline.copyWith(color: c.fg),
                  ),
                  const SizedBox(height: 8),
                  GuestsPicker(
                    value: guests,
                    onChanged: (int v) => setState(() => guests = v),
                  ),
                  if (waiters.isNotEmpty) ...<Widget>[
                    const SizedBox(height: 16),
                    Text(
                      'Ofitsiant',
                      style: RzType.headline.copyWith(color: c.fg),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: <Widget>[
                        ChoiceChip(
                          label: const Text('Ofitsiantsiz'),
                          selected: waiterId == null,
                          materialTapTargetSize: MaterialTapTargetSize.padded,
                          onSelected: (_) => setState(() => waiterId = null),
                        ),
                        for (final PosStaffRef w in waiters)
                          ChoiceChip(
                            label: Text(w.name),
                            selected: waiterId == w.id,
                            materialTapTargetSize: MaterialTapTargetSize.padded,
                            onSelected: (_) => setState(() => waiterId = w.id),
                          ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ),
          actions: <Widget>[
            RzButton(
              label: 'Bekor qilish',
              variant: RzButtonVariant.ghost,
              onPressed: () => Navigator.of(context).pop(),
            ),
            RzButton(
              label: 'Ochish',
              icon: Icons.add,
              size: RzButtonSize.lg,
              onPressed: () => Navigator.of(context)
                  .pop(OpenOrderRequest(guests: guests, waiterId: waiterId)),
            ),
          ],
        );
      },
    );
  },
);

/// Joyda buyurtma ochadi: oyna → `POST /pos/orders` (tasdiq so'ralsa — menejer). Bekor qilinsa yoki rad etilsa
/// `null` (xato SnackBar'da).
Future<PosOrder?> openTableOrder(
  BuildContext context, {
  required OpenTarget target,
}) async {
  final PosScope scope = PosScope.of(context);
  final List<PosStaffRef> waiters = <PosStaffRef>[
    for (final PosStaffRef s
        in scope.session.bootstrap?.staff ?? const <PosStaffRef>[])
      if (s.role == 'waiter') s,
  ];
  final OpenOrderRequest? req = await showOpenOrderDialog(
    context,
    target: target,
    waiters: waiters,
    onOpenBookings: scope.session.can(PosPermissions.bookingsManage)
        ? () => scope.go(CashierDestination.bookings)
        : null,
  );
  if (req == null || !context.mounted) return null;
  final ScaffoldMessengerState? messenger = ScaffoldMessenger.maybeOf(context);
  try {
    return await withApproval<PosOrder>(
      context,
      (String? token) => scope.data.orders.open(
        channel: PosChannel.hall,
        tableId: target.tableId,
        guestsCount: req.guests,
        waiterId: req.waiterId,
        approvalToken: token,
      ),
    );
  } on PosApprovalCancelled {
    return null;
  } on PosException catch (e) {
    messenger?.showSnackBar(SnackBar(content: Text(posErrorText(e))));
    return null;
  }
}

/// Bootstrap'dagi joy (sahifa faqat `tableId` bilan ochilganda).
OpenTarget? openTargetFromBootstrap(PosBootstrap? boot, int tableId) {
  for (final PosTable t in boot?.tables ?? const <PosTable>[]) {
    if (t.id == tableId) {
      return OpenTarget(
        tableId: t.id,
        name: t.name,
        kind: t.kind,
        seats: t.seats,
      );
    }
  }
  return null;
}
