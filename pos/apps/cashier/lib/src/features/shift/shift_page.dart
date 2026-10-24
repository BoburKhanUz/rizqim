import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/limited_mode.dart';
import '../../shell/pos_scope.dart';
import '../../shell/routes.dart';
import '../billing/cashier_ui.dart';
import 'cash_movement_dialog.dart';
import 'shift_report_mapping.dart';
import 'x_report_view.dart';
import 'z_close_page.dart';

/// Smena (POS_SPEC 2.3; 13-ekranlar 3, 11, 12): boshlang'ich naqd bilan ochish, joriy smena xulosasi (tushum, to'lov
/// turlari, kassadagi naqd), kassaga kirim va chiqim (bozorlik, ish haqi, boshqa), X hisobot (ko'rish va chop etish),
/// Z — kupyura bo'yicha sanab yopish.
class ShiftPage extends StatefulWidget {
  const ShiftPage({super.key});

  @override
  State<ShiftPage> createState() => _ShiftPageState();
}

class _ShiftPageState extends State<ShiftPage> with RefreshingState {
  PosShiftWithReport? _current;
  PosException? _error;
  PosShift? _lastClosed;
  bool _started = false;
  bool _busy = false;
  final TextEditingController _opening = TextEditingController();
  final TextEditingController _note = TextEditingController();

  PosScope get _scope => PosScope.of(context);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      startRefreshing(
        _scope.realtime,
        filter: (PosEvent e) =>
            e is PosShiftChanged ||
            e is PosOrderBillChanged ||
            e is PosBookingChanged,
        pollInterval: const Duration(seconds: 30),
      );
      unawaited(refresh());
    }
  }

  @override
  void dispose() {
    _opening.dispose();
    _note.dispose();
    super.dispose();
  }

  @override
  Future<void> refresh() async {
    try {
      final PosShiftWithReport current = await _scope.data.shifts.current();
      if (!mounted) return;
      setState(() {
        _current = current;
        _error = null;
      });
      final PosShiftSummary? now = current.shift?.toSummary();
      if (_scope.session.shift?.uuid != now?.uuid) {
        _scope.session.updateShift(now);
      }
    } on PosException catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  Future<void> _open() async {
    final PosMoney opening = parseMoney(_opening.text) ?? PosMoney.zero;
    setState(() => _busy = true);
    final bool ok = await runGuarded(context, () async {
      final PosShift shift = await withApproval<PosShift>(
        context,
        (String? token) => _scope.data.shifts.open(
          openingCash: opening,
          note: _note.text.trim().isEmpty ? null : _note.text.trim(),
        ),
      );
      _scope.session.updateShift(shift.toSummary());
    });
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) {
      _opening.clear();
      _note.clear();
      _lastClosed = null;
      showPosMessage(context, 'Smena ochildi.');
      await refresh();
    }
  }

  Future<void> _movement({required bool incoming}) async {
    final PosShiftWithReport? cur = _current;
    if (cur?.shift == null) return;
    final PosCashMovement? m = await showCashMovementDialog(
      context,
      shiftUuid: cur!.shift!.uuid,
      incoming: incoming,
      expectedCash: cur.report == null
          ? null
          : ShiftReportView(cur.report!).expectedCash,
    );
    if (m != null && mounted) await refresh();
  }

  Future<void> _xReport() async {
    final PosShift? shift = _current?.shift;
    if (shift == null) return;
    await runGuarded(context, () async {
      final PosShiftWithReport x = await _scope.data.shifts.xReport(shift.uuid);
      if (!mounted || x.report == null) return;
      await showXReport(context, x.shift ?? shift, x.report!);
    });
  }

  Future<void> _zClose() async {
    final PosShift? shift = _current?.shift;
    if (shift == null) return;
    PosShiftReport? report = _current?.report;
    try {
      report = (await _scope.data.shifts.xReport(shift.uuid)).report ?? report;
    } on PosException {
      // Oxirgi X hisobot bilan davom etamiz
    }
    if (!mounted || report == null) return;
    final PosShift? closed = await CashierRoutes.push<PosShift>(
      context,
      ZClosePage(shift: shift, report: report),
    );
    if (closed != null && mounted) {
      setState(() => _lastClosed = closed);
      showPosMessage(context, 'Smena yopildi. Z-hisobot chop etildi.');
      await refresh();
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosShiftWithReport? cur = _current;
    final Widget body;
    if (cur == null) {
      body = _error == null
          ? const LoadingBox()
          : Padding(
              padding: const EdgeInsets.all(24),
              child: InlineError(
                message: _error!.isNetworkError
                    ? "Aloqa yo'q: smenani yuklab bo'lmadi."
                    : _error!.message,
                onRetry: () => unawaited(refresh()),
              ),
            );
    } else if (cur.shift == null || !cur.shift!.isOpen) {
      body = _closedView(c);
    } else {
      // P3-31: rebuilt when the limited mode starts or ends (Z waits for the offline queue)
      return Material(
        color: c.page,
        child: OfflineStatusBuilder(
          builder: (BuildContext context, OfflineStatus _) =>
              _openView(c, cur.shift!, cur.report),
        ),
      );
    }
    return Material(color: c.page, child: body);
  }

  Widget _closedView(RzColors c) {
    final PosSession session = _scope.session;
    final bool can =
        session.can(PosPermissions.openShift) ||
        session.needsApproval(PosPermissions.openShift);
    final PosShift? z = _lastClosed;
    return ListView(
      padding: const EdgeInsets.all(24),
      children: <Widget>[
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 560),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                if (z != null) ...<Widget>[
                  PosCard(
                    title:
                        'Smena yopildi${z.zNumber == null ? '' : ' · Z-${z.zNumber}'}',
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: <Widget>[
                        if (z.expectedCash != null)
                          AmountRow(
                            label: 'Kutilgan naqd',
                            amount: z.expectedCash!,
                          ),
                        if (z.countedCash != null)
                          AmountRow(label: 'Sanalgan', amount: z.countedCash!),
                        if (z.difference != null)
                          AmountRow(
                            label: 'Farq',
                            amount: z.difference!,
                            bold: true,
                            tone: z.difference!.isZero
                                ? StatusTone.success
                                : (z.difference!.isNegative
                                      ? StatusTone.danger
                                      : StatusTone.warning),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
                PosCard(
                  title: 'Smenani ochish',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      Text(
                        "Smena ochilmagan. Smenani oching: to'lov qabul qilish, kassa harakatlari, X va Z hisobot shu "
                        'yerda.',
                        style: RzType.body.copyWith(color: c.fgMuted),
                      ),
                      const SizedBox(height: 16),
                      MoneyField(
                        fieldKey: const ValueKey<String>('opening-cash'),
                        controller: _opening,
                        label: "Kassadagi boshlang'ich naqd",
                        helperText:
                            "Maydalash puli: kassada hozir qancha naqd bor",
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _note,
                        decoration: const InputDecoration(
                          labelText: 'Izoh (ixtiyoriy)',
                        ),
                      ),
                      const SizedBox(height: 16),
                      RzButton(
                        key: const ValueKey<String>('shift-open'),
                        label: 'Smenani ochish',
                        icon: Icons.lock_open_rounded,
                        size: RzButtonSize.lg,
                        onPressed: can && !_busy ? _open : null,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _openView(RzColors c, PosShift shift, PosShiftReport? report) {
    final ShiftReportView? v = report == null ? null : ShiftReportView(report);
    final PosSession session = _scope.session;
    // P3-31: no cash movements and no Z until the offline queue has reached the server (POS_SPEC 5.4)
    final PosOffline? offline = _scope.data.offline;
    final bool limited = offline?.active ?? false;
    final bool cashOps =
        !limited &&
        (session.can(PosPermissions.cashInOut) ||
            session.needsApproval(PosPermissions.cashInOut));
    final bool canClose =
        !limited &&
        (session.can(PosPermissions.closeShift) ||
            session.needsApproval(PosPermissions.closeShift));
    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        PosCard(
          child: Wrap(
            spacing: 16,
            runSpacing: 12,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: <Widget>[
              const TonePill(
                label: 'Smena ochiq',
                tone: StatusTone.success,
                icon: Icons.lock_open_rounded,
              ),
              Text(
                <String>[
                  if (shift.businessDate != null)
                    'Biznes kun ${_day(shift.businessDate!)}',
                  if (shift.openedAt != null)
                    'ochildi ${dm(shift.openedAt!)} ${hm(shift.openedAt!)}',
                  if (shift.openedBy?.name != null) shift.openedBy!.name!,
                  if (shift.deviceName != null) shift.deviceName!,
                ].join(' · '),
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        if (v != null)
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: <Widget>[
              StatTile(label: 'Sof tushum', value: v.net.format()),
              StatTile(label: 'Cheklar', value: '${v.bills}'),
              StatTile(
                label: 'Kassadagi naqd',
                value: v.expectedCash.format(),
                tone: StatusTone.success,
              ),
              if (v.averageBill.isPositive)
                StatTile(label: "O'rtacha chek", value: v.averageBill.format()),
            ],
          ),
        const SizedBox(height: 16),
        Wrap(
          spacing: 12,
          runSpacing: 12,
          children: <Widget>[
            RzButton(
              label: 'Kirim',
              icon: Icons.south_west_rounded,
              size: RzButtonSize.lg,
              variant: RzButtonVariant.secondary,
              onPressed: cashOps ? () => _movement(incoming: true) : null,
            ),
            RzButton(
              key: const ValueKey<String>('cash-out'),
              label: 'Chiqim (bozorlik, ish haqi)',
              icon: Icons.north_east_rounded,
              size: RzButtonSize.lg,
              variant: RzButtonVariant.secondary,
              onPressed: cashOps ? () => _movement(incoming: false) : null,
            ),
          ],
        ),
        const SizedBox(height: 16),
        PosCard(
          title: 'X va Z hisobot',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                "X — smenani yopmasdan oraliq hisobot. Z — kassadagi naqdni sanab smenani yopish. Ochiq buyurtma yoki "
                'fiskal kutayotgan chek bo\'lsa smena yopilmaydi.',
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
              if (limited) ...<Widget>[
                const SizedBox(height: 12),
                NoticeBanner(
                  key: const ValueKey<String>('z-blocked'),
                  icon: Icons.cloud_off_rounded,
                  tone: StatusTone.danger,
                  message: (offline?.store.pendingCount ?? 0) > 0
                      ? "Internetsiz rejim: navbatda ${offline!.store.pendingCount} ta amal serverga yetmagan. Navbat bo'shaguncha smena yopilmaydi (Z), kirim-chiqim yozilmaydi."
                      : "Internet yo'q: smenani yopish (Z) va kirim-chiqim internet tiklangach.",
                ),
              ],
              const SizedBox(height: 12),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: <Widget>[
                  RzButton(
                    key: const ValueKey<String>('x-report'),
                    label: 'X hisobot',
                    icon: Icons.summarize_outlined,
                    size: RzButtonSize.lg,
                    variant: RzButtonVariant.secondary,
                    onPressed: _xReport,
                  ),
                  RzButton(
                    key: const ValueKey<String>('z-open'),
                    label: 'Smenani yopish (Z)',
                    icon: Icons.lock_clock_outlined,
                    size: RzButtonSize.lg,
                    onPressed: canClose ? _zClose : null,
                  ),
                ],
              ),
            ],
          ),
        ),
        if (v != null) ...<Widget>[
          const SizedBox(height: 16),
          LayoutBuilder(
            builder: (BuildContext context, BoxConstraints box) {
              final Widget tenders = PosCard(
                title: "To'lov turlari",
                child: _tenders(c, v),
              );
              final Widget moves = PosCard(
                title: 'Kassa harakatlari',
                child: _movements(c, report!),
              );
              if (box.maxWidth < 900) {
                return Column(
                  children: <Widget>[
                    tenders,
                    const SizedBox(height: 16),
                    moves,
                  ],
                );
              }
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Expanded(child: tenders),
                  const SizedBox(width: 16),
                  Expanded(child: moves),
                ],
              );
            },
          ),
        ],
      ],
    );
  }

  static String _day(String iso) =>
      iso.length >= 10 ? '${iso.substring(8, 10)}.${iso.substring(5, 7)}' : iso;

  Widget _tenders(RzColors c, ShiftReportView v) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: <Widget>[
      for (final ({String tender, int count, PosMoney amount}) t in v.byTender)
        if (PosTender.fromWire(t.tender)?.isMoneyReceived ?? true)
          AmountRow(
            label: '${tenderLabel(t.tender)} (${t.count})',
            amount: t.amount,
          ),
      const Divider(height: 20),
      Text('Pul emas', style: RzType.footnote.copyWith(color: c.fgMuted)),
      for (final ({String tender, int count, PosMoney amount}) t in v.byTender)
        if (!(PosTender.fromWire(t.tender)?.isMoneyReceived ?? true))
          AmountRow(
            label: '${tenderLabel(t.tender)} (${t.count})',
            amount: t.amount,
            muted: true,
          ),
      if (v.depositsTaken.isPositive)
        AmountRow(
          label: 'Olingan avanslar',
          amount: v.depositsTaken,
          muted: true,
        ),
      if (v.creditRepayments.isPositive)
        AmountRow(
          label: "Qarz to'lovlari",
          amount: v.creditRepayments,
          muted: true,
        ),
    ],
  );

  Widget _movements(RzColors c, PosShiftReport report) {
    final List<PosCashMovement> list = report.movements
        .where((PosCashMovement m) => m.type != 'sale')
        .toList();
    if (list.isEmpty) {
      return Text(
        "Hali kassa harakati yo'q.",
        style: RzType.body.copyWith(color: c.fgMuted),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        for (final PosCashMovement m in list.reversed)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Row(
              children: <Widget>[
                Icon(
                  movementIn(m)
                      ? Icons.south_west_rounded
                      : Icons.north_east_rounded,
                  color: movementIn(m) ? c.successSoftFg : c.dangerSoftFg,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        movementLabel(m),
                        style: RzType.body.copyWith(color: c.fg),
                      ),
                      Text(
                        <String>[
                          if (m.createdAt != null) hm(m.createdAt!),
                          if (m.employee?.name != null) m.employee!.name!,
                          ?m.note,
                          if (m.user?.name != null) m.user!.name!,
                        ].join(' · '),
                        style: RzType.footnote.copyWith(color: c.fgMuted),
                      ),
                    ],
                  ),
                ),
                Text(
                  '${movementIn(m) ? '+' : '−'}${m.amount.formatAmount()}',
                  style: RzType.body.copyWith(
                    color: movementIn(m) ? c.successSoftFg : c.dangerSoftFg,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
