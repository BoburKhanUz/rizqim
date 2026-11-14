import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';
import '../billing/payment_messages.dart';
import 'banquet_format.dart';
import 'banquet_printing.dart';

/// P6-53: tadbirni kassada bekor qilish (`banquets.manage`): avval hisob (necha kun qoldi, qoida foizi, to'langan,
/// ushlab qolinadigan va qaytariladigan qism, har to'lov qaysi yo'l bilan qaytadi), menejer o'z summasini sabab bilan
/// berishi mumkin; tasdiqlanganda naqd qaytarish shu kassaning ochiq smenasidan (`pos.refund` yoki menejer PIN'i),
/// qaytarish cheklari va "Bekor qilish haqi" cheki chop etiladi. Natija — yangilangan jadval.
Future<PosBanquetSchedule?> showBanquetCancelDialog(
  BuildContext context, {
  required PosBanquetEvent event,
}) => showScopedDialog<PosBanquetSchedule>(
  context,
  (BuildContext context) => BanquetCancelDialog(event: event),
  barrierDismissible: false,
);

class BanquetCancelDialog extends StatefulWidget {
  const BanquetCancelDialog({super.key, required this.event});

  final PosBanquetEvent event;

  @override
  State<BanquetCancelDialog> createState() => _BanquetCancelDialogState();
}

class _BanquetCancelDialogState extends State<BanquetCancelDialog> {
  final TextEditingController _reason = TextEditingController();
  final TextEditingController _retained = TextEditingController();
  final TextEditingController _override = TextEditingController();
  final LostRequest _lost = LostRequest();
  PosBanquetCancellationQuote? _quote;
  String? _error;
  bool _busy = false;
  bool _started = false;
  bool _own = false;

  static const List<String> _presets = <String>[
    "Mijoz voz kechdi",
    "Sana ko'chirildi",
    'Boshqa joy tanlandi',
  ];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      unawaited(_load());
    }
  }

  @override
  void dispose() {
    _reason.dispose();
    _retained.dispose();
    _override.dispose();
    super.dispose();
  }

  String? get _retainedValue {
    if (!_own) return null;
    final PosMoney? m = parseMoney(_retained.text);
    return m?.toApiString();
  }

  Future<void> _load() async {
    try {
      final PosBanquetCancellationQuote q = await PosScope.of(context)
          .data
          .banquetPayments
          .cancellationQuote(widget.event.uuid, retained: _retainedValue);
      if (mounted) {
        setState(() {
          _quote = q;
          _error = null;
        });
      }
    } on PosException catch (e) {
      if (mounted) {
        setState(
          () => _error = e is PosOfflineUnavailable
              ? banquetsOfflineText
              : e.message,
        );
      }
    }
  }

  Future<void> _cancel() async {
    final String reason = _reason.text.trim();
    if (reason.length < 3) {
      setState(() => _error = 'Bekor qilish sababini yozing.');
      return;
    }
    final PosBanquetCancellationQuote? q = _quote;
    if (q == null) return;
    if (q.overridden && _override.text.trim().isEmpty) {
      setState(
        () =>
            _error = "Ushlab qolinadigan summani o'zgartirish sababini yozing.",
      );
      return;
    }
    final PosScope scope = PosScope.of(context);
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final PosBanquetSchedule s = await _lost.run<PosBanquetSchedule>(
        context,
        '$reason|$_retainedValue|${_override.text}',
        (String? token, String? key) => scope.data.banquetPayments.cancel(
          widget.event.uuid,
          reason: reason,
          retained: _retainedValue,
          overrideReason: q.overridden ? _override.text.trim() : null,
          approvalToken: token,
          idempotencyKey: key,
        ),
      );
      if (!mounted) return;
      // The refund receipts (cash from the drawer) and the retained part's sale receipt
      bool cash = false;
      for (final PosBanquetPaymentEntry e in s.entries) {
        if (e.type != 'refund' && e.type != 'retain') continue;
        final bool drawer = e.type == 'refund' && e.tender == 'cash';
        await printBanquetEntry(
          context,
          event: widget.event,
          entry: e,
          openDrawer: drawer && !cash,
        );
        cash = cash || drawer;
        if (!mounted) return;
      }
      showPosMessage(
        context,
        q.paid.isZero
            ? 'Tadbir bekor qilindi, zal bo\'shadi.'
            : 'Tadbir bekor qilindi: qaytarildi ${q.refund.format()}, ushlab qolindi ${q.retained.format()}.',
      );
      Navigator.pop(context, s);
    } on PosNetworkException {
      if (mounted) {
        setState(
          () => _error = "Aloqa yo'q: holat noma'lum. Qayta yuborish xavfsiz — ikki marta bajarilmaydi.",
        );
      }
    } on PosApprovalCancelled {
      // nothing
    } on PosException catch (e) {
      if (mounted) {
        setState(
          () => _error = switch (e) {
            final PosOfflineUnavailable _ => banquetsOfflineText,
            final PosValidationException v => v.firstError,
            _ => paymentRefusalText(e) ?? e.message,
          },
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosBanquetCancellationQuote? q = _quote;
    return AlertDialog(
      title: const Text('Tadbirni bekor qilish'),
      content: SizedBox(
        width: 520,
        child: SingleChildScrollView(
          child: Column(
            key: const ValueKey<String>('banquet-cancel-dialog'),
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                '${widget.event.displayName} · ${banquetDate(widget.event.eventDate)}',
                style: RzType.headline.copyWith(color: c.fg),
              ),
              const SizedBox(height: 12),
              if (q == null && _error == null) const LoadingBox(),
              if (q != null) ...<Widget>[
                Text(
                  'Tadbirgacha ${q.daysBefore} kun: qoida bo\'yicha to\'langanning ${q.rulePercent}% ushlab qolinadi.',
                  key: const ValueKey<String>('banquet-cancel-rule'),
                  style: RzType.body.copyWith(color: c.fg),
                ),
                const SizedBox(height: 8),
                AmountRow(label: "To'langan", amount: q.paid),
                AmountRow(
                  key: const ValueKey<String>('banquet-cancel-retained'),
                  label: 'Ushlab qolinadi (bekor qilish haqi)',
                  amount: q.retained,
                  bold: true,
                ),
                AmountRow(
                  key: const ValueKey<String>('banquet-cancel-refund'),
                  label: 'Qaytariladi',
                  amount: q.refund,
                  bold: true,
                  tone: StatusTone.success,
                ),
                for (final PosBanquetCancellationPart p in q.rows)
                  if (p.refund.isPositive)
                    AmountRow(
                      label:
                          '  ${p.kind.label}: ${refundPathLabel(p.refundPath)}',
                      amount: p.refund,
                      muted: true,
                    ),
                if (q.cashRefund.isPositive) ...<Widget>[
                  const SizedBox(height: 8),
                  NoticeBanner(
                    message:
                        "Naqd ${q.cashRefund.format()} shu kassadan beriladi (ochiq smena). Qaytarish huquqi bo'lmasa — menejer PIN'i so'raladi.",
                    icon: Icons.payments_outlined,
                  ),
                ],
                if (q.paid.isPositive) ...<Widget>[
                  const SizedBox(height: 8),
                  CheckboxListTile(
                    key: const ValueKey<String>('banquet-cancel-own'),
                    contentPadding: EdgeInsets.zero,
                    value: _own,
                    title: const Text(
                      "Ushlab qolinadigan summani o'zim yozaman",
                    ),
                    onChanged: (bool? v) {
                      setState(() => _own = v ?? false);
                      if (!_own) unawaited(_load());
                    },
                  ),
                  if (_own) ...<Widget>[
                    MoneyField(
                      fieldKey: const ValueKey<String>('banquet-cancel-amount'),
                      controller: _retained,
                      label: "Ushlab qolinadi (0 — ${q.paid.format()})",
                      onChanged: (_) => unawaited(_load()),
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      key: const ValueKey<String>('banquet-cancel-override'),
                      controller: _override,
                      decoration: const InputDecoration(
                        labelText: "O'zgartirish sababi (egaga yuboriladi)",
                      ),
                    ),
                  ],
                ],
              ],
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  for (final String p in _presets)
                    ChoiceChip(
                      label: Text(p),
                      selected: _reason.text == p,
                      onSelected: (_) => setState(() => _reason.text = p),
                    ),
                ],
              ),
              const SizedBox(height: 8),
              TextField(
                key: const ValueKey<String>('banquet-cancel-reason'),
                controller: _reason,
                decoration: const InputDecoration(labelText: 'Sabab'),
                onChanged: (_) => setState(() {}),
              ),
              if (_error != null) ...<Widget>[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  key: const ValueKey<String>('banquet-cancel-error'),
                  style: RzType.body.copyWith(color: c.dangerSoftFg),
                ),
              ],
            ],
          ),
        ),
      ),
      actions: <Widget>[
        RzButton(
          label: 'Orqaga',
          variant: RzButtonVariant.ghost,
          onPressed: _busy ? null : () => Navigator.pop(context),
        ),
        RzButton(
          key: const ValueKey<String>('banquet-cancel-submit'),
          label: 'Bekor qilish',
          icon: Icons.event_busy_outlined,
          variant: RzButtonVariant.danger,
          onPressed: _busy || q == null ? null : _cancel,
        ),
      ],
    );
  }
}
