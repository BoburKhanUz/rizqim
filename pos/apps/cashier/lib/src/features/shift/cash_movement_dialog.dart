import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';
import '../billing/receipts.dart';
import 'shift_report_mapping.dart';

/// Kassaga kirim yoki kassadan chiqim (POS_SPEC 2.3): bozorlik, ish haqi (kimga — majburiy), boshqa chiqim yoki kirim;
/// kategoriya, summa, izoh ("Boshqa" uchun majburiy). `pos.cash_in_out` yoki menejer tasdig'i; kassa qutisi ochiladi.
Future<PosCashMovement?> showCashMovementDialog(
  BuildContext context, {
  required String shiftUuid,
  required bool incoming,
  PosMoney? expectedCash,
}) => showScopedDialog<PosCashMovement>(
  context,
  (BuildContext _) => _CashMovementDialog(
    shiftUuid: shiftUuid,
    incoming: incoming,
    expectedCash: expectedCash,
  ),
);

class _CashMovementDialog extends StatefulWidget {
  const _CashMovementDialog({
    required this.shiftUuid,
    required this.incoming,
    this.expectedCash,
  });

  final String shiftUuid;
  final bool incoming;
  final PosMoney? expectedCash;

  @override
  State<_CashMovementDialog> createState() => _CashMovementDialogState();
}

class _CashMovementDialogState extends State<_CashMovementDialog> {
  late PosCashMovementType _type = widget.incoming
      ? PosCashMovementType.cashIn
      : PosCashMovementType.purchase;
  late String _category = movementCategories[_type]!.keys.first;
  int? _employee;
  final TextEditingController _amount = TextEditingController();
  final TextEditingController _note = TextEditingController();
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _amount.dispose();
    _note.dispose();
    super.dispose();
  }

  void _setType(PosCashMovementType t) => setState(() {
    _type = t;
    _category = movementCategories[t]!.keys.first;
    _error = null;
  });

  Future<void> _submit() async {
    final PosMoney? amount = parseMoney(_amount.text);
    final String note = _note.text.trim();
    setState(() {
      _error = amount == null || !amount.isPositive
          ? 'Summani kiriting.'
          : _type == PosCashMovementType.wage && _employee == null
          ? 'Kimga berilganini tanlang.'
          : _category == 'other' && note.isEmpty
          ? '"Boshqa" uchun izoh yozing: nimaga berildi.'
          : !widget.incoming &&
                widget.expectedCash != null &&
                amount > widget.expectedCash!
          ? "Kassada buncha naqd pul yo'q: ${widget.expectedCash!.format()}."
          : null;
    });
    if (_error != null) return;
    setState(() => _busy = true);
    final PosScope scope = PosScope.of(context);
    try {
      final PosCashMovement m = await withApproval<PosCashMovement>(
        context,
        (String? token) => scope.data.shifts.cashMovement(
          widget.shiftUuid,
          type: _type,
          category: _category,
          amount: amount!,
          note: note.isEmpty ? null : note,
          employeeId: _type == PosCashMovementType.wage ? _employee : null,
          approvalToken: token,
        ),
      );
      if (!mounted) return;
      ReceiptPrinter(scope).openDrawer();
      showPosMessage(
        context,
        '${movementLabel(m)}: ${m.amount.format()} yozildi.',
      );
      Navigator.pop(context, m);
    } on Object catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        if (e is PosValidationException) {
          setState(() => _error = e.firstError);
        } else {
          showPosError(context, e);
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final List<PosStaffRef> staff =
        PosScope.of(context).session.bootstrap?.staff ?? const <PosStaffRef>[];
    return AlertDialog(
      title: Text(widget.incoming ? 'Kassaga kirim' : 'Kassadan chiqim'),
      content: SizedBox(
        width: 480,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              if (!widget.incoming) ...<Widget>[
                ChoiceRow<PosCashMovementType>(
                  options: const <PosCashMovementType, String>{
                    PosCashMovementType.purchase: 'Bozorlik',
                    PosCashMovementType.wage: 'Ish haqi',
                    PosCashMovementType.cashOut: 'Boshqa chiqim',
                  },
                  selected: _type,
                  onSelected: _setType,
                ),
                const SizedBox(height: 16),
              ],
              Text('Turi', style: RzType.footnote.copyWith(color: c.fgMuted)),
              const SizedBox(height: 6),
              ChoiceRow<String>(
                options: movementCategories[_type]!,
                selected: _category,
                onSelected: (String v) => setState(() => _category = v),
              ),
              if (_type == PosCashMovementType.wage) ...<Widget>[
                const SizedBox(height: 16),
                Text(
                  'Kimga',
                  style: RzType.footnote.copyWith(color: c.fgMuted),
                ),
                const SizedBox(height: 6),
                ChoiceRow<int>(
                  options: <int, String>{
                    for (final PosStaffRef s in staff) s.id: s.name,
                  },
                  selected: _employee,
                  onSelected: (int id) => setState(() => _employee = id),
                ),
              ],
              const SizedBox(height: 16),
              MoneyField(
                fieldKey: const ValueKey<String>('movement-amount'),
                controller: _amount,
                label: 'Summa',
                helperText: widget.expectedCash == null || widget.incoming
                    ? null
                    : 'Kassada: ${widget.expectedCash!.format()}',
              ),
              const SizedBox(height: 12),
              TextField(
                key: const ValueKey<String>('movement-note'),
                controller: _note,
                decoration: InputDecoration(
                  labelText: _category == 'other' ? 'Izoh (majburiy)' : 'Izoh',
                ),
              ),
              if (_error != null) ...<Widget>[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  style: RzType.body.copyWith(color: c.dangerSoftFg),
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
          onPressed: () => Navigator.pop(context),
        ),
        RzButton(
          key: const ValueKey<String>('movement-submit'),
          label: widget.incoming ? 'Kirim qilish' : 'Chiqim qilish',
          onPressed: _busy ? null : _submit,
        ),
      ],
    );
  }
}
