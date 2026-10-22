import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../common/connection_banner.dart';
import '../../common/errors.dart';
import '../../common/guests_stepper.dart';
import '../../common/sheet.dart';
import '../../shell/waiter_scope.dart';

/// Bo'sh joyni ochish: mehmonlar soni → buyurtma (`POST /pos/orders`). Ochilgan buyurtma bilan yopiladi; yopib
/// qo'yilsa `null`.
Future<PosOrder?> showOpenPlaceSheet(
  BuildContext context, {
  required FloorTable table,
  FloorHall? hall,
}) => showWaiterSheet<PosOrder>(
  context,
  builder: (BuildContext context) =>
      OpenPlaceSheet(table: table, hallName: hall?.name),
);

class OpenPlaceSheet extends StatefulWidget {
  const OpenPlaceSheet({super.key, required this.table, this.hallName});

  final FloorTable table;
  final String? hallName;

  @override
  State<OpenPlaceSheet> createState() => _OpenPlaceSheetState();
}

class _OpenPlaceSheetState extends State<OpenPlaceSheet> {
  late int _guests = _initialGuests();
  bool _busy = false;
  String? _error;

  /// Tarmoq xatosidan keyin shu amalni xuddi shu kalit bilan takrorlash (server ikki marta ochmaydi).
  String? _retryKey;

  int _initialGuests() {
    final int fromBooking = widget.table.reservation?.guests ?? 0;
    if (fromBooking > 0) return fromBooking.clamp(1, 99);
    final int seats = widget.table.seats ?? 4;
    return (seats < 4 ? seats : 4).clamp(1, 99);
  }

  Future<void> _open() async {
    final PosData data = WaiterScope.of(context).data;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final PosOrder order = await data.orders.open(
        channel: PosChannel.hall,
        tableId: widget.table.id,
        guestsCount: _guests,
        idempotencyKey: _retryKey,
      );
      if (mounted) Navigator.of(context).pop(order);
    } on Object catch (e) {
      if (e is PosNetworkException) _retryKey = e.idempotencyKey;
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = describeError(e);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final FloorTable t = widget.table;
    final PosData data = WaiterScope.of(context).data;
    final PosMoney? minSpend = t.minSpend;
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            SheetHeader(
              title: "${t.name} ni ochish",
              subtitle: <String>[
                ?widget.hallName,
                if (t.seats != null) "${t.seats} o'rin",
              ].join(' · '),
            ),
            const SizedBox(height: 20),
            GuestsStepper(value: _guests, onChanged: _setGuests),
            if (minSpend != null && minSpend.isPositive) ...<Widget>[
              const SizedBox(height: 14),
              Text(
                t.minSpendMode == 'per_person'
                    ? 'Minimal summa: ${minSpend.format()} (kishi boshiga)'
                    : 'Minimal summa: ${minSpend.format()}',
                textAlign: TextAlign.center,
                style: RzType.body.copyWith(color: c.warningSoftFg),
              ),
            ],
            if (_error != null) ...<Widget>[
              const SizedBox(height: 12),
              InlineError(message: _error!),
            ],
            const SizedBox(height: 20),
            StreamBuilder<PosConnectivityState>(
              stream: data.connectivity.changes,
              initialData: data.connectivity.state,
              builder: (BuildContext context, _) => RzButton(
                label: 'Ochish',
                icon: Icons.check_rounded,
                size: RzButtonSize.lg,
                fullWidth: true,
                loading: _busy,
                onPressed: isOnline(data.connectivity) ? _open : null,
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _setGuests(int value) {
    _retryKey = null;
    setState(() => _guests = value);
  }
}
