import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';
import '../billing/receipts.dart';
import 'shift_report_mapping.dart';

/// "Choy pulini berish" (P6-22): kassadagi naqd choy pulini xodimga berish — kassadan `wage` chiqimi, kategoriya `tips`
/// (X/Z da `cash.tip_payouts`, ish haqiga kirmaydi). `tips.manage` yoki menejer PIN'i: sahifa so'ragan tasdiq
/// ([approvalToken]) yozuv bilan birga yuboriladi va server uni bir marta ishlatadi (u `pos.cash_in_out` o'rniga ham
/// o'tadi). Server smenadagi naqd choy pulidan va xodimga tegishlisidan ortig'ini bermaydi (409). Yozildi — harakat,
/// aks holda `null`.
Future<PosCashMovement?> showTipPayoutDialog(
  BuildContext context, {
  required String shiftUuid,
  required ShiftReportView report,
  String? approvalToken,
}) => showScopedDialog<PosCashMovement>(
  context,
  (BuildContext _) => _TipPayoutDialog(
    shiftUuid: shiftUuid,
    report: report,
    approvalToken: approvalToken,
  ),
);

class _TipPayoutDialog extends StatefulWidget {
  const _TipPayoutDialog({
    required this.shiftUuid,
    required this.report,
    this.approvalToken,
  });

  final String shiftUuid;
  final ShiftReportView report;
  final String? approvalToken;

  @override
  State<_TipPayoutDialog> createState() => _TipPayoutDialogState();
}

class _TipPayoutDialogState extends State<_TipPayoutDialog> {
  int? _staff;
  final TextEditingController _amount = TextEditingController();
  final TextEditingController _note = TextEditingController();
  String? _error;
  bool _busy = false;

  /// Javobsiz qolgan yozuv: aynan o'zi (kalit, tasdiq) qayta yuboriladi.
  final LostRequest _lost = LostRequest();

  /// Sahifa so'ragan tasdiq: birinchi yuborishda ishlatiladi.
  late String? _granted = widget.approvalToken;

  PosMoney get _left => widget.report.cashTipsLeft;

  @override
  void initState() {
    super.initState();
    final List<PosTipStaffTotal> byStaff = widget.report.tips.byStaff;
    if (byStaff.length == 1) _pick(byStaff.single.staffId);
  }

  @override
  void dispose() {
    _amount.dispose();
    _note.dispose();
    super.dispose();
  }

  void _pick(int id) {
    _staff = id;
    _error = null;
    PosMoney? own;
    for (final PosTipStaffTotal s in widget.report.tips.byStaff) {
      if (s.staffId == id) own = s.amount;
    }
    final PosMoney suggested = own == null || own > _left ? _left : own;
    _amount.text = suggested.isPositive ? moneyText(suggested) : '';
  }

  Future<void> _submit() async {
    final PosMoney? amount = parseMoney(_amount.text);
    final String note = _note.text.trim();
    setState(() {
      _error = _staff == null
          ? 'Kimga berilganini tanlang.'
          : amount == null || !amount.isPositive
          ? 'Summani kiriting.'
          : amount > widget.report.expectedCash
          ? "Kassada buncha naqd pul yo'q: ${widget.report.expectedCash.format()}."
          : null;
    });
    if (_error != null) return;
    setState(() => _busy = true);
    final PosScope scope = PosScope.of(context);
    try {
      final PosCashMovement m = await _lost.run<PosCashMovement>(
        context,
        'tips|${amount!.toApiString()}|$note|$_staff',
        (String? token, String? key) => scope.data.shifts.cashMovement(
          widget.shiftUuid,
          type: PosCashMovementType.wage,
          category: 'tips',
          amount: amount,
          note: note.isEmpty ? 'Choy puli' : note,
          employeeId: _staff,
          approvalToken: token,
          idempotencyKey: key,
        ),
        approvalToken: _granted,
      );
      _granted = null;
      if (!mounted) return;
      ReceiptPrinter(scope).openDrawer();
      showPosMessage(context, 'Choy puli berildi: ${m.amount.format()}.');
      Navigator.pop(context, m);
    } on PosNetworkException {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = "Aloqa yo'q: yozuv holati noma'lum. Qayta yuborish xavfsiz — ikki marta yozilmaydi.";
        });
      }
    } on Object catch (e) {
      // A refused payout (409: more than the tips) leaves the approval unused; an expired or used one is dropped
      if (e is PosApprovalRejected) _granted = null;
      if (mounted) {
        setState(() => _busy = false);
        if (e is PosValidationException) {
          setState(() => _error = e.firstError);
        } else if (e is PosException &&
            (e.reason == 'tip_payout_exceeds_tips' ||
                e.reason == 'tip_payout_exceeds_owed')) {
          // More than this shift's cash tips, or than the person is owed today: the server says how much
          setState(() => _error = e.message);
        } else {
          showPosError(context, e);
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosTipsSummary tips = widget.report.tips;
    final List<PosStaffRef> staff =
        PosScope.of(context).session.bootstrap?.staff ?? const <PosStaffRef>[];
    final Map<int, String> options = <int, String>{
      for (final PosTipStaffTotal s in tips.byStaff)
        s.staffId:
            '${s.name ?? 'Xodim #${s.staffId}'} · ${s.amount.formatAmount()}',
      for (final PosStaffRef s in staff)
        if (!tips.byStaff.any((PosTipStaffTotal t) => t.staffId == s.id))
          s.id: s.name,
    };
    return AlertDialog(
      title: const Text('Choy pulini berish'),
      content: SizedBox(
        width: 480,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              AmountRow(
                label: 'Kassadagi naqd choy puli',
                amount: widget.report.cashTips,
              ),
              if (widget.report.tipPayouts.isPositive)
                AmountRow(
                  label: 'Oldin berilgan',
                  amount: widget.report.tipPayouts,
                  negative: true,
                  muted: true,
                ),
              AmountRow(
                key: const ValueKey<String>('tip-payout-left'),
                label: 'Berilmagan',
                amount: _left,
                bold: true,
              ),
              if (tips.pool.isPositive)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    "Umumiy qozon ${tips.pool.format()}: ulushlarni menejer kun oxirida bo'ladi (panelda).",
                    style: RzType.footnote.copyWith(color: c.fgMuted),
                  ),
                ),
              const SizedBox(height: 16),
              Text('Kimga', style: RzType.footnote.copyWith(color: c.fgMuted)),
              const SizedBox(height: 6),
              ChoiceRow<int>(
                options: options,
                selected: _staff,
                onSelected: (int id) => setState(() => _pick(id)),
              ),
              const SizedBox(height: 16),
              MoneyField(
                fieldKey: const ValueKey<String>('tip-payout-amount'),
                controller: _amount,
                label: 'Summa',
                helperText:
                    'Kassada: ${widget.report.expectedCash.format()} (shu jumladan choy puli)',
              ),
              const SizedBox(height: 12),
              TextField(
                key: const ValueKey<String>('tip-payout-note'),
                controller: _note,
                decoration: const InputDecoration(labelText: 'Izoh'),
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
          key: const ValueKey<String>('tip-payout-submit'),
          label: 'Berish',
          icon: Icons.volunteer_activism_outlined,
          onPressed: _busy ? null : _submit,
        ),
      ],
    );
  }
}
