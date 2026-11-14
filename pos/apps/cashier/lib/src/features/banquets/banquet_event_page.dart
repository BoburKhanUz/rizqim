import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../counter/counter_format.dart';
import '../../shell/pos_scope.dart';
import '../../shell/routes.dart';
import '../billing/cashier_ui.dart';
import 'banquet_cancel_dialog.dart';
import 'banquet_form_page.dart';
import 'banquet_format.dart';
import 'banquet_pay_dialog.dart';
import 'banquet_printing.dart';
import 'banquet_settlement_page.dart';

/// P6-51…P6-54: tadbir kartasi kassada: mijoz, zallar, vaqt, mehmonlar, paket va qatorlar, jamilar, ogohlantirishlar,
/// to'lov jadvali (to'langan / kutilmoqda / muddati o'tgan — "To'lash"), amallar: o'zgartirish, "Tadbirni boshlash"
/// (tadbir kuni buyurtmasi oddiy buyurtma ekranida, sarlavhada tadbir nomi), kelgan mehmonlar, "Yakuniy hisob",
/// bekor qilish, chop etish (shartnoma, ishlab chiqarish varag'i; chek printerida — A4 panelda). Internetsiz — oddiy
/// xabar.
class BanquetEventPage extends StatefulWidget {
  const BanquetEventPage({
    super.key,
    required this.uuid,
    this.halls = const <PosBanquetCalendarHall>[],
  });

  final String uuid;
  final List<PosBanquetCalendarHall> halls;

  @override
  State<BanquetEventPage> createState() => _BanquetEventPageState();
}

class _BanquetEventPageState extends State<BanquetEventPage> {
  PosBanquetEvent? _event;
  PosBanquetSchedule? _schedule;
  String? _error;
  bool _busy = false;
  bool _started = false;

  PosScope get _scope => PosScope.of(context);
  PosSession get _session => _scope.session;
  bool get _manage => _session.can(PosPermissions.banquetsManage);
  bool get _takePayment => _session.can(PosPermissions.takePayment);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      unawaited(_load());
    }
  }

  Future<void> _load() async {
    try {
      final PosBanquetEvent e = await _scope.data.banquets.show(widget.uuid);
      final PosBanquetSchedule s = await _scope.data.banquetPayments.schedule(
        widget.uuid,
      );
      if (!mounted) return;
      setState(() {
        _event = e;
        _schedule = s;
        _error = null;
      });
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

  Future<void> _run(Future<void> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
    } on Object catch (e) {
      if (mounted) {
        showPosError(
          context,
          e is PosOfflineUnavailable ? banquetsOfflineText : e,
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pay(PosBanquetPaymentRow row) async {
    final PosBanquetEvent? e = _event;
    if (e == null) return;
    if (_session.shift == null) {
      showPosMessage(
        context,
        "Smena ochilmagan: to'lov olish uchun avval smenani oching.",
      );
      return;
    }
    final PosBanquetSchedule? s = await showBanquetPayDialog(
      context,
      event: e,
      row: row,
    );
    if (s != null && mounted) await _load();
  }

  Future<void> _reprint(PosBanquetPaymentRow row) async {
    final PosBanquetEvent? e = _event;
    final PosBanquetPaymentEntry? entry = _schedule?.entries
        .where(
          (PosBanquetPaymentEntry x) =>
              x.type == 'payment' && x.paymentUuid == row.uuid,
        )
        .firstOrNull;
    if (e == null || entry == null) return;
    await _run(() async {
      await printBanquetEntry(
        context,
        event: e,
        entry: entry,
        kind: row.kind,
        copy: true,
      );
    });
  }

  Future<void> _edit() async {
    final PosBanquetEvent? e = _event;
    if (e == null) return;
    final PosBanquetEvent? saved = await CashierRoutes.push<PosBanquetEvent>(
      context,
      BanquetFormPage(event: e, halls: widget.halls),
    );
    if (saved != null && mounted) {
      showPosMessage(context, 'Tadbir saqlandi.');
      await _load();
    }
  }

  String get _orderTitle => 'Tadbir: ${_event?.displayName ?? ''}';

  Future<void> _start() async {
    final PosBanquetEvent? e = _event;
    if (e == null) return;
    await _run(() async {
      final String? order = await _scope.data.banquetOps.start(
        e.uuid,
        version: e.version,
      );
      if (!mounted || order == null) return;
      await _load();
      if (!mounted) return;
      await CashierRoutes.openOrder<void>(
        context,
        orderUuid: order,
        placeTitle: _orderTitle,
      );
      if (mounted) await _load();
    });
  }

  Future<void> _openOrder() async {
    final String? order = _event?.orderUuid;
    if (order == null) return;
    await CashierRoutes.openOrder<void>(
      context,
      orderUuid: order,
      placeTitle: _orderTitle,
    );
    if (mounted) await _load();
  }

  Future<void> _guests() async {
    final PosBanquetEvent? e = _event;
    if (e == null) return;
    final int? n = await showDialog<int>(
      context: context,
      builder: (BuildContext context) =>
          _GuestsDialog(initial: e.guestsActual ?? e.guestsGuaranteed),
    );
    if (n == null || !mounted) return;
    await _run(() async {
      await _scope.data.banquetOps.setGuestsActual(
        e.uuid,
        n,
        version: e.version,
      );
      if (!mounted) return;
      showPosMessage(context, 'Kelgan mehmonlar: $n kishi.');
      await _load();
    });
  }

  Future<void> _settlement() async {
    final PosBanquetEvent? e = _event;
    if (e == null) return;
    await CashierRoutes.push<void>(context, BanquetSettlementPage(event: e));
    if (mounted) await _load();
  }

  Future<void> _cancel() async {
    final PosBanquetEvent? e = _event;
    if (e == null) return;
    final PosBanquetSchedule? s = await showBanquetCancelDialog(
      context,
      event: e,
    );
    if (s != null && mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosBanquetEvent? e = _event;
    return Scaffold(
      backgroundColor: c.page,
      appBar: AppBar(
        title: e == null
            ? const Text('Tadbir')
            : Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    e.displayName,
                    style: RzType.title.copyWith(color: c.fg),
                  ),
                  Text(
                    '${longDay(banquetDay(e.eventDate))} · ${banquetTime(e)}',
                    style: RzType.footnote.copyWith(color: c.fgMuted),
                  ),
                ],
              ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Yangilash',
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _busy ? null : _load,
          ),
        ],
      ),
      body: e == null
          ? (_error == null
                ? const LoadingBox()
                : Padding(
                    padding: const EdgeInsets.all(16),
                    child: NoticeBanner(
                      key: const ValueKey<String>('banquet-event-error'),
                      message: _error!,
                      action: TextButton(
                        onPressed: _load,
                        child: const Text('Qayta urinish'),
                      ),
                    ),
                  ))
          : LayoutBuilder(
              builder: (BuildContext context, BoxConstraints box) {
                final bool wide = box.maxWidth >= 900;
                final List<Widget> left = <Widget>[
                  _details(c, e),
                  const SizedBox(height: 12),
                  _lines(c, e),
                ];
                final List<Widget> right = <Widget>[
                  _scheduleCard(c, e),
                  const SizedBox(height: 12),
                  _actions(c, e),
                ];
                if (!wide) {
                  return SingleChildScrollView(
                    key: const ValueKey<String>('banquet-event'),
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: <Widget>[
                        ..._warnings(e),
                        ...left,
                        const SizedBox(height: 12),
                        ...right,
                      ],
                    ),
                  );
                }
                return SingleChildScrollView(
                  key: const ValueKey<String>('banquet-event'),
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      ..._warnings(e),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: left,
                            ),
                          ),
                          const SizedBox(width: 16),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: right,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              },
            ),
    );
  }

  List<Widget> _warnings(PosBanquetEvent e) => <Widget>[
    for (final PosBanquetWarning w in e.warnings) ...<Widget>[
      NoticeBanner(
        key: ValueKey<String>('banquet-warning-${w.code}'),
        message: w.message,
      ),
      const SizedBox(height: 12),
    ],
  ];

  Widget _fact(RzColors c, String label, String value, {Key? key}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        SizedBox(
          width: 150,
          child: Text(label, style: RzType.body.copyWith(color: c.fgMuted)),
        ),
        Expanded(
          child: Text(
            value,
            key: key,
            style: RzType.body.copyWith(color: c.fg),
          ),
        ),
      ],
    ),
  );

  Widget _details(RzColors c, PosBanquetEvent e) {
    final bool overdue = _schedule?.summary.overdue ?? e.overdue;
    return PosCard(
      title: e.eventTypeLabel,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: <Widget>[
              BanquetStatusPill(e.status),
              if (overdue) const BanquetOverduePill(),
            ],
          ),
          const SizedBox(height: 8),
          _fact(c, 'Mijoz', e.customerName),
          if (e.customerPhone != null) _fact(c, 'Telefon', e.customerPhone!),
          _fact(c, 'Zal', banquetHalls(e)),
          _fact(c, 'Vaqt', banquetTime(e)),
          _fact(
            c,
            'Mehmonlar',
            'kafolatlangan ${e.guestsGuaranteed}, minimal ${e.guestsMinimum}'
                '${e.guestsActual == null ? '' : ', kelgan ${e.guestsActual}'} · hisobda ${e.guestsBilled}',
            key: const ValueKey<String>('banquet-event-guests'),
          ),
          if (e.packageName != null) _fact(c, 'Paket', e.packageName!),
          if (e.contractNo != null) _fact(c, 'Shartnoma', '№ ${e.contractNo}'),
          if (e.status == PosBanquetStatus.hold && e.holdExpiresAt != null)
            _fact(
              c,
              'Band muddati',
              '${dm(e.holdExpiresAt!, year: true)} ${hm(e.holdExpiresAt!)} gacha (zaklad olinmasa bo\'shaydi)',
            ),
          if (e.frozen) _fact(c, 'Narxlar', 'Shartnoma kuni muzlatilgan'),
          if (e.quoteCurrency != null && e.quoteTotal != null)
            _fact(
              c,
              'Kotirovka',
              '${e.quoteTotal} ${e.quoteCurrency} (faqat ma\'lumot: hisob so\'mda)',
            ),
          if (e.note != null && e.note!.isNotEmpty) _fact(c, 'Izoh', e.note!),
        ],
      ),
    );
  }

  Widget _lines(RzColors c, PosBanquetEvent e) => PosCard(
    title: 'Hisob',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        for (final PosBanquetLine l in e.lines)
          AmountRow(
            label: l.perPerson
                ? '${l.name} · ${l.unitPrice.format()} × ${l.persons}'
                : (l.quantity == PosQuantity.one
                      ? l.name
                      : '${l.name} × ${l.quantity.format()}'),
            amount: l.amount,
          ),
        const Divider(height: 20),
        AmountRow(
          key: const ValueKey<String>('banquet-event-total'),
          label: 'Jami',
          amount: e.total,
          bold: true,
          large: true,
        ),
        AmountRow(label: "To'langan", amount: e.paidTotal),
        AmountRow(
          key: const ValueKey<String>('banquet-event-outstanding'),
          label: 'Qoldiq',
          amount: e.outstanding,
          bold: true,
          tone: e.outstanding.isPositive ? StatusTone.warning : null,
        ),
      ],
    ),
  );

  Widget _scheduleCard(RzColors c, PosBanquetEvent e) {
    final PosBanquetSchedule? s = _schedule;
    final bool payable =
        _takePayment &&
        (e.status == PosBanquetStatus.hold ||
            e.status == PosBanquetStatus.confirmed ||
            e.status == PosBanquetStatus.inProgress);
    return PosCard(
      title: "To'lov jadvali",
      child: s == null
          ? const LoadingBox()
          : Column(
              key: const ValueKey<String>('banquet-schedule'),
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                if (s.rows.isEmpty)
                  Text(
                    "Jadvalda qator yo'q.",
                    style: RzType.body.copyWith(color: c.fgMuted),
                  ),
                for (final PosBanquetPaymentRow r in s.rows)
                  _rowTile(c, e, r, payable),
                if (s.summary.overdue) ...<Widget>[
                  const SizedBox(height: 8),
                  NoticeBanner(
                    message:
                        "Muddati o'tgan: ${s.summary.overdueCount} ta, ${s.summary.overdueAmount.format()}.",
                    tone: StatusTone.danger,
                    icon: Icons.warning_amber_rounded,
                  ),
                ],
                if (s.cancellation != null) ...<Widget>[
                  const SizedBox(height: 8),
                  NoticeBanner(
                    message:
                        'Bekor qilindi (${s.cancellation!.daysBefore} kun oldin): qaytarildi ${s.cancellation!.refund.format()}, ushlab qolindi ${s.cancellation!.retained.format()}.',
                    tone: StatusTone.neutral,
                  ),
                ],
                const SizedBox(height: 8),
                Text(
                  "Bank o'tkazmasi — boshqaruv panelida yoziladi.",
                  style: RzType.footnote.copyWith(color: c.fgMuted),
                ),
              ],
            ),
    );
  }

  Widget _rowTile(
    RzColors c,
    PosBanquetEvent e,
    PosBanquetPaymentRow r,
    bool payable,
  ) {
    final (String label, StatusTone tone, IconData icon) = paymentRowState(r);
    final String paid = r.status == PosBanquetPaymentStatus.paid
        ? ' · ${banquetTenderLabel(r.tender)}'
        : '';
    return Padding(
      key: ValueKey<String>('banquet-row-${r.uuid}'),
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  r.label,
                  style: RzType.body.copyWith(
                    color: c.fg,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              Text(
                r.amount.format(),
                style: RzType.body.copyWith(
                  color: c.fg,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Wrap(
            spacing: 8,
            runSpacing: 4,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: <Widget>[
              TonePill(label: label, tone: tone, icon: icon),
              Text(
                '${banquetDate(r.dueDate)}$paid',
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
              if (r.isDue && payable)
                RzButton(
                  key: ValueKey<String>('banquet-pay-${r.uuid}'),
                  label: "To'lash",
                  size: RzButtonSize.md,
                  onPressed: _busy ? null : () => _pay(r),
                ),
              if (r.status == PosBanquetPaymentStatus.paid &&
                  r.tender != 'bank_transfer')
                IconButton(
                  key: ValueKey<String>('banquet-reprint-${r.uuid}'),
                  tooltip: "Bo'nak chekini qayta chop etish",
                  icon: const Icon(Icons.print_outlined),
                  onPressed: _busy ? null : () => _reprint(r),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _actions(RzColors c, PosBanquetEvent e) {
    final PosBanquetStatus st = e.status;
    final bool module = _session.hasModule(PosModule.banquet);
    final bool editable = st.isEditable;
    final List<Widget> buttons = <Widget>[
      if (st == PosBanquetStatus.confirmed &&
          module &&
          (_manage || _takePayment))
        RzButton(
          key: const ValueKey<String>('banquet-start'),
          label: 'Tadbirni boshlash',
          icon: Icons.play_circle_outline_rounded,
          variant: RzButtonVariant.success,
          loading: _busy,
          onPressed: _busy ? null : _start,
        ),
      if (st == PosBanquetStatus.inProgress && e.orderUuid != null)
        RzButton(
          key: const ValueKey<String>('banquet-open-order'),
          label: 'Tadbir buyurtmasi',
          icon: Icons.restaurant_menu_rounded,
          onPressed: _busy ? null : _openOrder,
        ),
      if ((st == PosBanquetStatus.confirmed ||
              st == PosBanquetStatus.inProgress) &&
          _manage)
        RzButton(
          key: const ValueKey<String>('banquet-guests'),
          label: 'Kelgan mehmonlar',
          icon: Icons.groups_outlined,
          variant: RzButtonVariant.secondary,
          onPressed: _busy ? null : _guests,
        ),
      if ((st == PosBanquetStatus.confirmed ||
              st == PosBanquetStatus.inProgress ||
              st == PosBanquetStatus.settled) &&
          _takePayment)
        RzButton(
          key: const ValueKey<String>('banquet-settlement'),
          label: 'Yakuniy hisob',
          icon: Icons.receipt_long_outlined,
          variant: st == PosBanquetStatus.inProgress
              ? RzButtonVariant.primary
              : RzButtonVariant.secondary,
          onPressed: _busy ? null : _settlement,
        ),
      if (editable && _manage)
        RzButton(
          key: const ValueKey<String>('banquet-edit'),
          label: "O'zgartirish",
          icon: Icons.edit_outlined,
          variant: RzButtonVariant.secondary,
          onPressed: _busy ? null : _edit,
        ),
      if (module && st != PosBanquetStatus.cancelled)
        RzButton(
          key: const ValueKey<String>('banquet-print-sheet'),
          label: "Ishlab chiqarish varag'i",
          icon: Icons.print_outlined,
          variant: RzButtonVariant.ghost,
          onPressed: _busy
              ? null
              : () => _run(() async {
                  final bool ok = await printBanquetProductionSheet(
                    context,
                    e.uuid,
                  );
                  if (ok && mounted) {
                    showPosMessage(
                      context,
                      "Ishlab chiqarish varag'i chop etildi.",
                    );
                  }
                }),
        ),
      if (module && _manage && st != PosBanquetStatus.cancelled)
        RzButton(
          key: const ValueKey<String>('banquet-print-contract'),
          label: 'Shartnoma',
          icon: Icons.description_outlined,
          variant: RzButtonVariant.ghost,
          onPressed: _busy
              ? null
              : () => _run(() async {
                  final bool ok = await printBanquetContract(context, e.uuid);
                  if (ok && mounted) {
                    showPosMessage(context, 'Shartnoma chop etildi.');
                  }
                }),
        ),
      if (editable && _manage)
        RzButton(
          key: const ValueKey<String>('banquet-cancel'),
          label: 'Bekor qilish',
          icon: Icons.event_busy_outlined,
          variant: RzButtonVariant.danger,
          onPressed: _busy ? null : _cancel,
        ),
    ];
    return PosCard(
      title: 'Amallar',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (buttons.isEmpty)
            Text(
              "Bu tadbirda amal yo'q.",
              style: RzType.body.copyWith(color: c.fgMuted),
            ),
          Wrap(spacing: 8, runSpacing: 8, children: buttons),
          const SizedBox(height: 8),
          Text(
            banquetsA4Note,
            style: RzType.footnote.copyWith(color: c.fgMuted),
          ),
        ],
      ),
    );
  }
}

class _GuestsDialog extends StatefulWidget {
  const _GuestsDialog({required this.initial});

  final int initial;

  @override
  State<_GuestsDialog> createState() => _GuestsDialogState();
}

class _GuestsDialogState extends State<_GuestsDialog> {
  late final TextEditingController _text = TextEditingController(
    text: '${widget.initial}',
  );

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final int? n = int.tryParse(_text.text.trim());
    return AlertDialog(
      title: const Text('Kelgan mehmonlar'),
      content: SizedBox(
        width: 360,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            TextField(
              key: const ValueKey<String>('banquet-guests-field'),
              controller: _text,
              autofocus: true,
              keyboardType: TextInputType.number,
              inputFormatters: <TextInputFormatter>[
                FilteringTextInputFormatter.digitsOnly,
              ],
              decoration: const InputDecoration(suffixText: 'kishi'),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 8),
            Text(
              "Hisob kafolatlangandan kam bo'lmaydi: kam kelsa ham kafolatlangan son olinadi.",
              style: RzType.footnote.copyWith(color: context.rz.fgMuted),
            ),
          ],
        ),
      ),
      actions: <Widget>[
        RzButton(
          label: 'Bekor qilish',
          variant: RzButtonVariant.ghost,
          onPressed: () => Navigator.pop(context),
        ),
        RzButton(
          key: const ValueKey<String>('banquet-guests-save'),
          label: 'Saqlash',
          onPressed: n == null || n < 1
              ? null
              : () => Navigator.pop(context, n),
        ),
      ],
    );
  }
}
