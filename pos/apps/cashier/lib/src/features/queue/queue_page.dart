import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/pos_scope.dart';
import '../../shell/routes.dart';
import '../billing/billing_page.dart';
import '../../printing/label_printing.dart';
import '../billing/cashier_ui.dart';
import '../order/takeaway_widgets.dart' show pickupTimeLabel;
import '../settings/cashier_prefs.dart';

/// Barcha kanallar navbati (POS_SPEC 2.5; 13-ekranlar 5, 14): qabul kutayotgan Telegram va mobil buyurtmalar (ovoz,
/// N daqiqadan oshsa ogohlantirish; qabul qilish yoki rad etish), "fiskal kutmoqda" ro'yxati (qayta urinish) va hamma
/// ochiq buyurtmalar (zal, olib ketish, onlayn). Real-time hodisa yoki polling bilan yangilanadi.
class QueuePage extends StatefulWidget {
  const QueuePage({super.key});

  @override
  State<QueuePage> createState() => _QueuePageState();
}

enum _Filter { all, hall, takeaway, online }

class _QueuePageState extends State<QueuePage> with RefreshingState {
  List<PosOnlineOrder> _pending = const <PosOnlineOrder>[];
  List<PosOpenOrder> _finished = const <PosOpenOrder>[];
  List<PosOpenOrder>? _open;
  PosException? _error;
  bool _started = false;
  bool _onlineAllowed = true;
  final Set<String> _seen = <String>{};
  final Set<String> _busy = <String>{};
  bool _firstLoad = true;
  _Filter _filter = _Filter.all;
  Timer? _ticker;

  PosScope get _scope => PosScope.of(context);

  OnlineOrderRepository get _online => OnlineOrderRepository(_scope.data.http);

  late final CashierLabels _labels = CashierLabels(
    printing: _scope.printing,
    bootstrap: () => _scope.session.bootstrap,
    catalog: () => _scope.data.catalog.cached(PosChannel.takeaway),
    now: _scope.services.now,
  );

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      unawaited(CashierPrefs.instance.load());
      startRefreshing(
        _scope.realtime,
        filter: (PosEvent e) =>
            e is PosOrderEvent ||
            e is PosOrderBillChanged ||
            e is PosOrderBillRequested ||
            e is PosFiscalReceiptUpdated ||
            e is PosOrderItemsChanged,
      );
      // Kutish vaqti va ogohlantirish yangilanib turadi
      _ticker = Timer.periodic(const Duration(seconds: 30), (_) {
        if (mounted) setState(() {});
      });
      unawaited(refresh());
    }
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  @override
  Future<void> refresh() async {
    final int? branch = _scope.session.branchId;
    try {
      final List<PosOpenOrder> open = await _scope.data.orders.openOrders(
        branchId: branch,
      );
      List<PosOnlineOrder> pending = _pending;
      if (_onlineAllowed) {
        try {
          pending = await _online.pending(branchId: branch);
        } on PosForbidden {
          _onlineAllowed = false;
          pending = const <PosOnlineOrder>[];
        } on PosNotFound {
          // Server onlayn navbatni bermaydi: faqat POS buyurtmalari
          _onlineAllowed = false;
          pending = const <PosOnlineOrder>[];
        } on PosException catch (e) {
          if (e.isNetworkError) rethrow;
        }
      }
      List<PosOpenOrder> finished = _finished;
      try {
        // P3-26: shu ish kunida yopilgan POS buyurtmalari (chek nusxasi va qaytarish)
        finished = await _scope.data.orders.closedOrders(branchId: branch);
      } on PosException catch (e) {
        if (e.isNetworkError) rethrow;
      }
      if (!mounted) return;
      final bool fresh = pending.any(
        (PosOnlineOrder o) => !_seen.contains(o.uuid),
      );
      if (fresh && !_firstLoad && CashierPrefs.instance.sound) {
        unawaited(SystemSound.play(SystemSoundType.alert));
      }
      _seen.addAll(pending.map((PosOnlineOrder o) => o.uuid));
      _firstLoad = false;
      setState(() {
        _open = open;
        _pending = pending;
        _finished = finished;
        _error = null;
      });
    } on PosException catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  int get _alertMinutes =>
      _scope
          .session
          .bootstrap
          ?.branch
          .settings
          .alerts
          .unacceptedOnlineMinutes ??
      3;

  int _ageMinutes(DateTime? at) => at == null
      ? 0
      : _scope.services.now().difference(at).inMinutes.clamp(0, 100000);

  Future<void> _accept(PosOnlineOrder o) async {
    setState(() => _busy.add(o.uuid));
    final bool ok = await runGuarded(context, () => _online.accept(o.uuid));
    if (!mounted) return;
    setState(() => _busy.remove(o.uuid));
    if (ok) {
      // P6-32: a Telegram pickup gets its bag label (and its drinks their stickers) as it is accepted
      _labels.printLater(_labels.forOnline(o));
      showPosMessage(
        context,
        'Qabul qilindi: #${o.shortCode ?? ''}. Marka oshxonaga chiqadi.',
      );
    }
    await refresh();
  }

  Future<void> _reject(PosOnlineOrder o) async {
    final PosRejectReason? reason = await showDialog<PosRejectReason>(
      context: context,
      builder: (BuildContext context) => SimpleDialog(
        title: Text('#${o.shortCode ?? ''} buyurtmani rad etish'),
        children: <Widget>[
          for (final PosRejectReason r in PosRejectReason.values)
            SimpleDialogOption(
              onPressed: () => Navigator.pop(context, r),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 10),
                child: Text(r.label, style: RzType.headline),
              ),
            ),
        ],
      ),
    );
    if (reason == null || !mounted) return;
    setState(() => _busy.add(o.uuid));
    // P3-26: orders.cancel bo'lmagan kassir menejer tasdig'i (PIN) bilan rad etadi
    final bool ok = await runGuarded(
      context,
      () => withApproval<PosOnlineOrder>(
        context,
        (String? token) =>
            _online.reject(o.uuid, reason: reason, approvalToken: token),
      ),
    );
    if (!mounted) return;
    setState(() => _busy.remove(o.uuid));
    if (ok) {
      showPosMessage(
        context,
        'Rad etildi: #${o.shortCode ?? ''}. Mijozga xabar boradi.',
      );
    }
    await refresh();
  }

  /// Kutilayotgan sotuv cheklarini qayta so'raydi; chiqqanini chop etadi.
  Future<void> _retryFiscal(PosOpenOrder o) async {
    setState(() => _busy.add(o.uuid));
    await runGuarded(context, () async {
      final PosBilling billing = await _scope.data.billing.bills(o.uuid);
      var issued = 0;
      for (final PosBill b in billing.bills) {
        if (!b.awaitingReceipt || !b.fiscal.isPending) {
          continue;
        }
        final PosBillResult r = await _scope.data.billing.fiscalRetry(b.uuid);
        if (r.bill.fiscal.status == 'issued') {
          issued++;
          if (mounted) await printSale(context, r.bill, null);
        }
      }
      if (!mounted) return;
      showPosMessage(
        context,
        issued > 0
            ? 'Chek chiqdi va chop etildi.'
            : "Chek hali kutilmoqda: provayder javob bermadi.",
      );
    });
    if (!mounted) return;
    setState(() => _busy.remove(o.uuid));
    await refresh();
  }

  Future<void> _openOrder(PosOpenOrder o) async {
    if (!o.isPos) {
      showPosMessage(
        context,
        'Onlayn buyurtma #${o.shortCode ?? ''}: oshxonada. Holati KDS va boshqaruv panelida.',
      );
      return;
    }
    await CashierRoutes.openOrder<void>(context, orderUuid: o.uuid);
    if (mounted) await refresh();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final List<PosOpenOrder>? open = _open;
    Widget body;
    if (open == null) {
      body = _error == null
          ? const LoadingBox()
          : Padding(
              padding: const EdgeInsets.all(24),
              child: InlineError(
                message: _error!.isNetworkError
                    ? "Aloqa yo'q: navbatni yuklab bo'lmadi."
                    : _error!.message,
                onRetry: () => unawaited(refresh()),
              ),
            );
    } else if (open.isEmpty && _pending.isEmpty && _finished.isEmpty) {
      body = const EmptyState(
        icon: Icons.receipt_long_outlined,
        title: "Navbat bo'sh",
        message: "Barcha ochiq buyurtmalar (zal, olib ketish, onlayn) shu yerda bo'ladi.",
      );
    } else {
      body = LayoutBuilder(
        builder: (BuildContext context, BoxConstraints box) {
          final Widget online = _onlineColumn(c);
          final Widget rest = _openColumn(c, open);
          if (box.maxWidth < 900) {
            return ListView(
              padding: const EdgeInsets.all(16),
              children: <Widget>[online, const SizedBox(height: 16), rest],
            );
          }
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              SizedBox(
                width: (box.maxWidth * 0.4).clamp(380, 480),
                child: ListView(
                  padding: const EdgeInsets.all(16),
                  children: <Widget>[online],
                ),
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(0, 16, 16, 16),
                  children: <Widget>[rest],
                ),
              ),
            ],
          );
        },
      );
    }
    return Material(color: c.page, child: body);
  }

  Widget _onlineColumn(RzColors c) => PosCard(
    title: 'Yangi onlayn buyurtmalar (${_pending.length})',
    trailing: IconButton(
      tooltip: 'Yangilash',
      icon: const Icon(Icons.refresh_rounded),
      onPressed: () => unawaited(refresh()),
    ),
    child: !_onlineAllowed
        ? Text(
            "Onlayn buyurtmalarni ko'rish uchun ruxsat yo'q.",
            style: RzType.body.copyWith(color: c.fgMuted),
          )
        : _pending.isEmpty
        ? Text(
            "Qabul kutayotgan buyurtma yo'q. Yangisi kelsa ovoz chiqadi.",
            style: RzType.body.copyWith(color: c.fgMuted),
          )
        : Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              for (final PosOnlineOrder o in _pending) ...<Widget>[
                _onlineCard(c, o),
                const SizedBox(height: 12),
              ],
            ],
          ),
  );

  Widget _onlineCard(RzColors c, PosOnlineOrder o) {
    final int age = _ageMinutes(o.createdAt);
    final bool late = age >= _alertMinutes;
    final bool busy = _busy.contains(o.uuid);
    // Rad etish: orders.cancel, bo'lmasa menejer tasdig'i bilan (P3-26)
    final bool approvalNeeded = !_scope.session.can(
      PosPermissions.ordersCancel,
    );
    return DecoratedBox(
      decoration: BoxDecoration(
        color: late ? Color.alphaBlend(c.warningSoft, c.surface) : c.surface,
        borderRadius: BorderRadius.circular(RzRadius.control),
        border: Border.all(
          color: late ? c.warning : c.line,
          width: late ? 2 : 1,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                Icon(channelIcon(o.channel), color: c.info),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    '#${o.shortCode ?? ''} · ${channelLabel(o.channel)}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: RzType.headline.copyWith(color: c.fg),
                  ),
                ),
                Text(
                  o.totalPrice.format(),
                  style: RzType.headline.copyWith(color: c.fg),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              <String>[
                switch (o.type) {
                  'delivery' => 'Yetkazish',
                  'pickup' => 'Olib ketish',
                  'dine_in' => 'Zalda',
                  _ => o.type,
                },
                // P6-34: a Telegram pickup for later says its time
                if (_pickupOf(o.raw) case final PosPickupTime p)
                  'vaqti: ${pickupTimeLabel(p)}',
                ?o.customerName,
                ?o.customerPhone,
                if (o.isPaid)
                  "To'langan"
                else
                  "To'lov: ${o.paymentMethod == 'cash' ? 'naqd' : (o.paymentMethod ?? '')}",
              ].join(' · '),
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
            if (o.deliveryAddress != null)
              Text(
                o.deliveryAddress!,
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            const SizedBox(height: 8),
            for (final PosOnlineOrderItem i in o.items.take(6))
              Text(
                '${i.quantityLabel ?? ''} × ${i.displayName}',
                style: RzType.body.copyWith(color: c.fg),
              ),
            if (o.notes != null && o.notes!.isNotEmpty)
              Text(
                'Izoh: ${o.notes}',
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
            const SizedBox(height: 8),
            Row(
              children: <Widget>[
                Icon(
                  late ? Icons.warning_amber_rounded : Icons.schedule_rounded,
                  size: 18,
                  color: late ? c.warningSoftFg : c.fgMuted,
                ),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    late
                        ? '${minutesLabel(age)} qabul qilinmadi — $_alertMinutes daqiqadan oshdi'
                        : (age == 0
                              ? 'Hozir keldi'
                              : '${minutesLabel(age)} oldin'),
                    style: RzType.footnote.copyWith(
                      color: late ? c.warningSoftFg : c.fgMuted,
                      fontWeight: late ? FontWeight.w700 : FontWeight.w500,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: <Widget>[
                Expanded(
                  child: RzButton(
                    key: ValueKey<String>('accept-${o.uuid}'),
                    label: 'Qabul qilish',
                    icon: Icons.check_rounded,
                    variant: RzButtonVariant.success,
                    size: RzButtonSize.lg,
                    loading: busy,
                    onPressed: busy ? null : () => _accept(o),
                  ),
                ),
                const SizedBox(width: 8),
                Tooltip(
                  message: approvalNeeded
                      ? "Rad etish — menejer tasdig'i bilan"
                      : 'Rad etish',
                  child: RzButton(
                    key: ValueKey<String>('reject-${o.uuid}'),
                    label: 'Rad etish',
                    icon: approvalNeeded ? Icons.lock_outline_rounded : null,
                    variant: RzButtonVariant.danger,
                    size: RzButtonSize.lg,
                    onPressed: busy ? null : () => _reject(o),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _openColumn(RzColors c, List<PosOpenOrder> open) {
    final List<PosOpenOrder> fiscal = open
        .where((PosOpenOrder o) => o.fiscalPending)
        .toList();
    final List<PosOpenOrder> shown = open.where((PosOpenOrder o) {
      return switch (_filter) {
        _Filter.all => true,
        _Filter.hall => o.channel == 'pos_hall',
        _Filter.takeaway => o.channel == 'pos_takeaway',
        _Filter.online => !o.isPos,
      };
    }).toList();
    final PosMoney total = PosMoney.sum(
      open
          .where((PosOpenOrder o) => o.isPos)
          .map((PosOpenOrder o) => o.totalPrice),
    );
    // P6-34: the orders for later (counter and Telegram pickups) with their time, the soonest first
    final List<(PosOpenOrder, PosPickupTime)> later =
        <(PosOpenOrder, PosPickupTime)>[
          for (final PosOpenOrder o in open)
            if (_pickupOf(o.raw) case final PosPickupTime p when p.waiting)
              (o, p),
        ]..sort(
          ((PosOpenOrder, PosPickupTime) a, (PosOpenOrder, PosPickupTime) b) =>
              a.$2.pickupAt.compareTo(b.$2.pickupAt),
        );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (later.isNotEmpty) ...<Widget>[
          PosCard(
            key: const ValueKey<String>('queue-scheduled'),
            title: 'Vaqtga (${later.length})',
            trailing: Text(
              'Oshxonaga vaqtida chiqadi',
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                for (final (PosOpenOrder o, PosPickupTime p) in later)
                  ListTile(
                    key: ValueKey<String>('scheduled-${o.uuid}'),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 4),
                    leading: Icon(Icons.schedule_rounded, color: c.infoSoftFg),
                    title: Text(
                      '${pickupTimeLabel(p)} · ${_place(o)}',
                      style: RzType.headline.copyWith(color: c.fg),
                    ),
                    subtitle: Text(
                      <String>[
                        if (o.shortCode != null) '#${o.shortCode}',
                        if (o.raw['queue'] case {'number': final Object n})
                          'raqam $n',
                        '${o.itemsCount} taom',
                        if (p.kitchenFireAt != null)
                          'oshxonaga ${hm(p.kitchenFireAt!)}',
                      ].join(' · '),
                      style: RzType.footnote.copyWith(color: c.fgMuted),
                    ),
                    trailing: Text(
                      o.totalPrice.format(),
                      style: RzType.headline.copyWith(color: c.fg),
                    ),
                    onTap: () => _openOrder(o),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 16),
        ],
        if (fiscal.isNotEmpty) ...<Widget>[
          PosCard(
            title: 'Fiskal kutmoqda (${fiscal.length})',
            color: Color.alphaBlend(c.warningSoft, c.surface),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Text(
                  "Pul olingan, sotuv cheki chiqmagan: chek chiqmaguncha buyurtma yopilmaydi va smena yopilmaydi.",
                  style: RzType.footnote.copyWith(color: c.warningSoftFg),
                ),
                const SizedBox(height: 8),
                for (final PosOpenOrder o in fiscal)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(
                      Icons.hourglass_top_rounded,
                      color: c.warningSoftFg,
                    ),
                    title: Text(_place(o)),
                    subtitle: Text(
                      '#${o.shortCode ?? ''} · ${o.totalPrice.format()}',
                    ),
                    trailing: RzButton(
                      key: ValueKey<String>('fiscal-retry-${o.uuid}'),
                      label: 'Qayta urinish',
                      variant: RzButtonVariant.secondary,
                      loading: _busy.contains(o.uuid),
                      onPressed: () => _retryFiscal(o),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 16),
        ],
        PosCard(
          title: 'Ochiq buyurtmalar (${open.length})',
          trailing: Text(
            'Jami ${total.format()}',
            style: RzType.body.copyWith(color: c.fgMuted),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              ChoiceRow<_Filter>(
                options: <_Filter, String>{
                  _Filter.all: 'Hammasi',
                  _Filter.hall:
                      'Zal (${open.where((PosOpenOrder o) => o.channel == 'pos_hall').length})',
                  _Filter.takeaway:
                      'Olib ketish (${open.where((PosOpenOrder o) => o.channel == 'pos_takeaway').length})',
                  _Filter.online:
                      'Onlayn (${open.where((PosOpenOrder o) => !o.isPos).length})',
                },
                selected: _filter,
                onSelected: (_Filter f) => setState(() => _filter = f),
              ),
              const SizedBox(height: 8),
              if (shown.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: Text(
                    "Bu turda ochiq buyurtma yo'q.",
                    style: RzType.body.copyWith(color: c.fgMuted),
                  ),
                ),
              for (final PosOpenOrder o in shown) _orderRow(c, o),
            ],
          ),
        ),
        if (_finished.isNotEmpty) ...<Widget>[
          const SizedBox(height: 16),
          PosCard(
            title: 'Bugun yopilgan (${_finished.length})',
            trailing: Text(
              'Chek nusxasi, qaytarish va bekor qilish',
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                for (final PosOpenOrder o in _finished.take(30))
                  ListTile(
                    key: ValueKey<String>('finished-${o.uuid}'),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 4),
                    leading: Icon(
                      o.status == 'canceled'
                          ? Icons.cancel_outlined
                          : Icons.check_circle_outline_rounded,
                      color: o.status == 'canceled'
                          ? c.fgMuted
                          : c.successSoftFg,
                    ),
                    title: Text(
                      _place(o),
                      style: RzType.body.copyWith(color: c.fg),
                    ),
                    subtitle: Text(
                      <String>[
                        if (o.shortCode != null) '#${o.shortCode}',
                        if (o.closedAt != null) 'yopildi ${hm(o.closedAt!)}',
                      ].join(' · '),
                      style: RzType.footnote.copyWith(color: c.fgMuted),
                    ),
                    // P3-37: a fully refunded order may be cancelled from its bill screen
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        if (o.status == 'canceled')
                          const TonePill(
                            label: 'Bekor qilingan',
                            tone: StatusTone.neutral,
                          )
                        else if (o.paymentStatus == 'refunded')
                          const TonePill(
                            label: 'Pul qaytarilgan',
                            tone: StatusTone.warning,
                            icon: Icons.undo_rounded,
                          ),
                        const SizedBox(width: 8),
                        Text(
                          o.totalPrice.format(),
                          style: RzType.body.copyWith(color: c.fg),
                        ),
                      ],
                    ),
                    onTap: () async {
                      await CashierRoutes.openBilling<void>(
                        context,
                        orderUuid: o.uuid,
                      );
                      if (mounted) await refresh();
                    },
                  ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  /// P6-34: ro'yxat qatoridagi olib ketish vaqti (`pickup`), yo'q — `null`.
  static PosPickupTime? _pickupOf(Map<String, Object?> raw) {
    final Object? p = raw['pickup'];
    if (p is! Map) return null;
    try {
      return PosPickupTime.fromJson(PosJson.of(p));
    } on FormatException {
      return null;
    }
  }

  static String _place(PosOpenOrder o) =>
      o.table?.name ??
      o.tableRef ??
      (o.channel == 'pos_takeaway' ? 'Olib ketish' : channelLabel(o.channel));

  Widget _orderRow(RzColors c, PosOpenOrder o) {
    final int age = _ageMinutes(o.openedAt);
    return ListTile(
      key: ValueKey<String>('open-${o.uuid}'),
      contentPadding: const EdgeInsets.symmetric(horizontal: 4),
      minVerticalPadding: 10,
      onTap: () => _openOrder(o),
      leading: Icon(channelIcon(o.channel), color: c.fgMuted),
      title: Text(_place(o), style: RzType.headline.copyWith(color: c.fg)),
      subtitle: Text(
        <String>[
          if (o.shortCode != null) '#${o.shortCode}',
          if ((o.guestsCount ?? 0) > 0) '${o.guestsCount} mehmon',
          if (o.waiter?.name != null) o.waiter!.name!,
          '${o.itemsCount} taom',
          formatOpenDuration(Duration(minutes: age)),
        ].join(' · '),
        style: RzType.footnote.copyWith(color: c.fgMuted),
      ),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          if (_pickupOf(o.raw) case final PosPickupTime p when p.waiting)
            TonePill(
              label: pickupTimeLabel(p) ?? 'Vaqtga',
              tone: StatusTone.info,
              icon: Icons.schedule_rounded,
            )
          else if (o.fiscalPending)
            const TonePill(label: 'Fiskal kutmoqda', tone: StatusTone.warning)
          else if (o.billRequested && !o.billLocked)
            const TonePill(
              label: "Schyot so'raldi",
              tone: StatusTone.warning,
              icon: Icons.receipt_long_outlined,
            )
          else if (o.billLocked)
            const TonePill(
              label: 'Schyot chiqdi',
              tone: StatusTone.info,
              icon: Icons.print_outlined,
            ),
          const SizedBox(width: 8),
          Text(
            o.totalPrice.format(),
            style: RzType.headline.copyWith(color: c.fg),
          ),
        ],
      ),
    );
  }
}
