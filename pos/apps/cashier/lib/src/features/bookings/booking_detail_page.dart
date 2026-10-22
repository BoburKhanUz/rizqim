import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/pos_scope.dart';
import '../../shell/routes.dart';
import '../billing/cashier_ui.dart';
import '../billing/receipts.dart';
import '../billing/tender_form.dart';
import 'booking_form_page.dart';
import 'preorder_picker.dart';

/// Bron holati: yorliq, rang, ikonka.
(String, StatusTone, IconData) bookingStatus(
  PosBooking b,
) => switch (b.status) {
  'requested' => ("So'rov", StatusTone.warning, Icons.help_outline_rounded),
  'confirmed' => (
    'Tasdiqlangan',
    StatusTone.info,
    Icons.event_available_rounded,
  ),
  'arrived' => ('Keldi', StatusTone.success, Icons.how_to_reg_rounded),
  'completed' => ('Yakunlangan', StatusTone.neutral, Icons.done_all_rounded),
  'canceled' => ('Bekor qilingan', StatusTone.danger, Icons.event_busy_rounded),
  'no_show' => ('Kelmadi', StatusTone.danger, Icons.person_off_outlined),
  _ => (b.statusLabel ?? b.status, StatusTone.neutral, Icons.event_outlined),
};

/// Bron sahifasi: ma'lumot, holat amallari (tasdiqlash, "Keldi" → buyurtma, kelmadi, bekor qilish), oldindan buyurtma
/// (qo'shish, miqdor, olib tashlash) va avans (olish — bo'nak cheki; qaytarish yoki ushlab qolish — menejer tasdig'i).
class BookingDetailPage extends StatefulWidget {
  const BookingDetailPage({
    super.key,
    required this.booking,
    required this.rooms,
  });

  final PosBooking booking;
  final List<PosCalendarRoom> rooms;

  @override
  State<BookingDetailPage> createState() => _BookingDetailPageState();
}

class _BookingDetailPageState extends State<BookingDetailPage> {
  late PosBooking _b = widget.booking;
  bool _busy = false;
  bool _started = false;

  PosScope get _scope => PosScope.of(context);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      unawaited(_reload());
    }
  }

  Future<void> _reload() async {
    try {
      final PosBooking fresh = await _scope.data.bookings.get(_b.uuid);
      if (mounted) setState(() => _b = fresh);
    } on PosException {
      // Ro'yxatdagi nusxa bilan qolamiz
    }
  }

  String? get _roomName {
    final List<String> names = <String>[
      for (final PosCalendarRoom r in widget.rooms)
        if (_b.tableIds.contains(r.id) || r.id == _b.diningTableId) r.name,
    ];
    return names.isEmpty ? null : names.join(' + ');
  }

  Future<void> _act(
    Future<PosBooking> Function() action, {
    String? done,
  }) async {
    setState(() => _busy = true);
    final bool ok = await runGuarded(context, () async {
      final PosBooking r = await action();
      if (mounted) setState(() => _b = r);
    });
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok && done != null) showPosMessage(context, done);
    if (!ok) await _reload();
  }

  Future<void> _arrive() async {
    setState(() => _busy = true);
    final PosScope scope = _scope;
    String? orderUuid;
    try {
      final PosArrival a = await scope.data.bookings.arrive(
        _b.uuid,
        version: _b.version,
      );
      orderUuid = a.orderUuid;
      if (mounted) setState(() => _b = a.booking);
    } on PosConflict catch (e) {
      if (e.reason == 'booking_already_arrived') {
        orderUuid = e.data['order_uuid'] as String?;
      } else if (mounted) {
        showPosError(context, e);
      }
    } on Object catch (e) {
      if (mounted) showPosError(context, e);
    }
    if (!mounted) return;
    setState(() => _busy = false);
    if (orderUuid != null) {
      await CashierRoutes.openOrder<void>(context, orderUuid: orderUuid);
      if (mounted) await _reload();
    }
  }

  Future<void> _cancel() async {
    final String? reason = await askReason(
      context,
      title: 'Bronni bekor qilish',
      presets: const <String>[
        'Mijoz bekor qildi',
        'Sana o\'zgardi',
        "Telefon javob bermadi",
      ],
      confirmLabel: 'Bekor qilish',
      confirmVariant: RzButtonVariant.danger,
    );
    if (reason == null || !mounted) return;
    await _act(
      () => _scope.data.bookings.cancel(
        _b.uuid,
        reason: reason,
        version: _b.version,
      ),
      done: 'Bron bekor qilindi.',
    );
  }

  Future<void> _noShow() async {
    final bool ok = await confirmAction(
      context,
      title: 'Mehmon kelmadi',
      message: "Bron \"kelmadi\" deb belgilanadi va xona bo'shaydi. Avansni keyin qaytarish yoki ushlab qolish mumkin.",
      confirmLabel: 'Kelmadi',
      confirmVariant: RzButtonVariant.danger,
    );
    if (!ok || !mounted) return;
    await _act(
      () => _scope.data.bookings.noShow(_b.uuid, version: _b.version),
      done: 'Kelmadi deb belgilandi.',
    );
  }

  Future<void> _edit() async {
    final PosBooking? saved = await CashierRoutes.push<PosBooking>(
      context,
      BookingFormPage(rooms: widget.rooms, booking: _b),
    );
    if (saved != null && mounted) setState(() => _b = saved);
  }

  Future<void> _addItem() async {
    final PreorderDraft? d = await showPreorderPicker(
      context,
      start: _b.startsAt.toLocal(),
    );
    if (d == null || !mounted) return;
    await _act(
      () => _scope.data.bookings.addItem(
        _b.uuid,
        productVariantId: d.variant.id,
        quantity: d.quantity,
        dueAt: d.dueAt,
        version: _b.version,
      ),
    );
  }

  Future<void> _changeItem(PosBookingItem item) async {
    final PosQuantity? q = await showScopedDialog<PosQuantity>(
      context,
      (BuildContext context) => AlertDialog(
        title: Text(item.productName),
        content: SizedBox(
          width: 360,
          child: SingleChildScrollView(
            child: PosQuantityKeypad(
              unit: item.unit,
              initial: item.quantity,
              keySize: 56,
              submitLabel: 'Saqlash',
              onSubmit: (PosQuantity v) => Navigator.pop(context, v),
            ),
          ),
        ),
      ),
    );
    if (q == null || !mounted) return;
    await _act(
      () => _scope.data.bookings.updateItem(
        _b.uuid,
        item.id,
        quantity: q,
        version: _b.version,
      ),
    );
  }

  Future<void> _takeDeposit() async {
    final PosDepositResult? r = await showScopedDialog<PosDepositResult>(
      context,
      (BuildContext _) => _DepositDialog(booking: _b, room: _roomName),
    );
    if (r != null && mounted) setState(() => _b = r.booking);
  }

  Future<void> _settle(PosBookingPayment p, {required bool refund}) async {
    final String? reason = await askReason(
      context,
      title: refund ? 'Avansni qaytarish' : 'Avansni ushlab qolish',
      message: refund
          ? "Qaytarish uchun: mijoz arizasi, shaxsni tasdiqlovchi hujjat nusxasi, qaytarish dalolatnomasi va asl bo'nak "
                'cheki. Menejer tasdiqlaydi.'
          : "Mehmon kelmadi yoki kech bekor qildi: avans ushlab qolinadi. Menejer tasdiqlaydi.",
      presets: refund
          ? const <String>['Mijoz bekor qildi', 'Xona band bo\'lib qoldi']
          : const <String>['Mehmon kelmadi', 'Kech bekor qildi'],
      confirmLabel: refund ? 'Qaytarish' : 'Ushlab qolish',
      confirmVariant: RzButtonVariant.danger,
    );
    if (reason == null || !mounted) return;
    setState(() => _busy = true);
    await runGuarded(context, () async {
      final PosDepositResult r = await withApproval<PosDepositResult>(
        context,
        (String? token) => refund
            ? _scope.data.bookings.refundDeposit(
                _b.uuid,
                p.uuid,
                reason: reason,
                approvalToken: token,
              )
            : _scope.data.bookings.retainDeposit(
                _b.uuid,
                p.uuid,
                reason: reason,
                approvalToken: token,
              ),
      );
      if (!mounted) return;
      setState(() => _b = r.booking);
      if (refund && p.tender == 'cash') ReceiptPrinter.of(context).openDrawer();
      showPosMessage(
        context,
        refund ? 'Avans qaytarildi.' : 'Avans ushlab qolindi.',
      );
    });
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final (String label, StatusTone tone, IconData icon) = bookingStatus(_b);
    final bool active = _b.status == 'requested' || _b.status == 'confirmed';
    final bool started = !_scope.services.now().isBefore(_b.startsAt);
    return Scaffold(
      backgroundColor: c.page,
      appBar: AppBar(
        title: Text(_b.contactName ?? 'Bron'),
        actions: <Widget>[
          if (active)
            IconButton(
              tooltip: 'Tahrirlash',
              icon: const Icon(Icons.edit_outlined),
              onPressed: _edit,
            ),
          const SizedBox(width: 8),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          PosCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    TonePill(label: label, tone: tone, icon: icon),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        '${longDay(_b.startsAt)} · ${hm(_b.startsAt)}–${hm(_b.endsAt)}',
                        style: RzType.headline.copyWith(color: c.fg),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 20,
                  runSpacing: 8,
                  children: <Widget>[
                    if (_roomName != null)
                      _info(c, Icons.meeting_room_outlined, _roomName!),
                    if (_b.guestsPlanned != null)
                      _info(
                        c,
                        Icons.groups_outlined,
                        '${_b.guestsPlanned} kishi',
                      ),
                    if (_b.eventTypeLabel != null || _b.eventType != null)
                      _info(
                        c,
                        Icons.celebration_outlined,
                        _b.eventTypeLabel ?? eventTypes[_b.eventType] ?? '',
                      ),
                    if (_b.contactPhone != null)
                      _info(c, Icons.phone_outlined, _b.contactPhone!),
                    if (_b.sourceLabel != null)
                      _info(c, Icons.call_received_rounded, _b.sourceLabel!),
                    if (_b.minSpend != null)
                      _info(
                        c,
                        Icons.price_check_rounded,
                        'Minimal ${_b.minSpend!.format()}',
                      ),
                  ],
                ),
                if (_b.note != null && _b.note!.isNotEmpty) ...<Widget>[
                  const SizedBox(height: 8),
                  Text(_b.note!, style: RzType.body.copyWith(color: c.fgMuted)),
                ],
                if (_b.cancelReason != null) ...<Widget>[
                  const SizedBox(height: 8),
                  Text(
                    'Sabab: ${_b.cancelReason}',
                    style: RzType.body.copyWith(color: c.dangerSoftFg),
                  ),
                ],
                const SizedBox(height: 16),
                Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  children: <Widget>[
                    if (active || _b.status == 'arrived')
                      RzButton(
                        key: const ValueKey<String>('booking-arrive'),
                        label: _b.status == 'arrived'
                            ? 'Buyurtmani ochish'
                            : 'Keldi',
                        icon: Icons.how_to_reg_rounded,
                        size: RzButtonSize.lg,
                        variant: RzButtonVariant.success,
                        loading: _busy,
                        onPressed: _arrive,
                      ),
                    if (_b.status == 'requested')
                      RzButton(
                        label: 'Tasdiqlash',
                        icon: Icons.event_available_rounded,
                        size: RzButtonSize.lg,
                        variant: RzButtonVariant.secondary,
                        onPressed: _busy
                            ? null
                            : () => _act(
                                () => _scope.data.bookings.confirm(
                                  _b.uuid,
                                  version: _b.version,
                                ),
                                done: 'Bron tasdiqlandi.',
                              ),
                      ),
                    if (_b.status == 'confirmed' && started)
                      RzButton(
                        label: 'Kelmadi',
                        icon: Icons.person_off_outlined,
                        size: RzButtonSize.lg,
                        variant: RzButtonVariant.secondary,
                        onPressed: _busy ? null : _noShow,
                      ),
                    if (active)
                      RzButton(
                        label: 'Bekor qilish',
                        icon: Icons.event_busy_rounded,
                        size: RzButtonSize.lg,
                        variant: RzButtonVariant.ghost,
                        onPressed: _busy ? null : _cancel,
                      ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          LayoutBuilder(
            builder: (BuildContext context, BoxConstraints box) {
              final Widget items = _itemsCard(c, active);
              final Widget deposits = _depositsCard(c, active);
              if (box.maxWidth < 900) {
                return Column(
                  children: <Widget>[
                    items,
                    const SizedBox(height: 16),
                    deposits,
                  ],
                );
              }
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Expanded(child: items),
                  const SizedBox(width: 16),
                  Expanded(child: deposits),
                ],
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _info(RzColors c, IconData icon, String text) => Row(
    mainAxisSize: MainAxisSize.min,
    children: <Widget>[
      Icon(icon, size: 20, color: c.fgMuted),
      const SizedBox(width: 6),
      Text(text, style: RzType.body.copyWith(color: c.fg)),
    ],
  );

  Widget _itemsCard(RzColors c, bool active) => PosCard(
    title: 'Oldindan buyurtma',
    trailing: active
        ? TextButton.icon(
            key: const ValueKey<String>('booking-add-item'),
            onPressed: _busy ? null : _addItem,
            icon: const Icon(Icons.add_rounded),
            label: const Text("Taom qo'shish"),
          )
        : null,
    child: _b.items.isEmpty
        ? Text(
            "Oldindan buyurtma yo'q.",
            style: RzType.body.copyWith(color: c.fgMuted),
          )
        : Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              for (final PosBookingItem i in _b.items)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  onTap: active ? () => _changeItem(i) : null,
                  title: Text(
                    i.variantName == null || i.variantName!.isEmpty
                        ? i.productName
                        : '${i.productName} (${i.variantName})',
                  ),
                  subtitle: Text(
                    '${i.quantity.label(i.unit)}${i.dueAt == null ? '' : ' · tayyor ${hm(i.dueAt!)}'}',
                  ),
                  trailing: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      Text(
                        i.unitPrice.timesQuantity(i.quantity).formatAmount(),
                      ),
                      if (active)
                        IconButton(
                          tooltip: 'Olib tashlash',
                          icon: const Icon(Icons.delete_outline_rounded),
                          onPressed: _busy
                              ? null
                              : () => _act(
                                  () => _scope.data.bookings.removeItem(
                                    _b.uuid,
                                    i.id,
                                  ),
                                ),
                        ),
                    ],
                  ),
                ),
              const Divider(height: 16),
              AmountRow(
                label: 'Jami',
                amount: PosMoney.sum(
                  _b.items.map(
                    (PosBookingItem i) => i.unitPrice.timesQuantity(i.quantity),
                  ),
                ),
                bold: true,
              ),
              Text(
                '"Keldi" bosilganda taomlar xona hisobiga ushlab turilgan holda o\'tadi.',
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ],
          ),
  );

  Widget _depositsCard(RzColors c, bool active) => PosCard(
    title: 'Avans',
    trailing: active
        ? TextButton.icon(
            key: const ValueKey<String>('booking-deposit'),
            onPressed: _busy ? null : _takeDeposit,
            icon: const Icon(Icons.add_card_rounded),
            label: const Text('Avans olish'),
          )
        : null,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        AmountRow(label: 'Olingan', amount: _b.depositTotal),
        AmountRow(
          label: 'Mavjud (ayiriladi)',
          amount: _b.depositAvailable,
          bold: true,
          tone: StatusTone.success,
        ),
        if (_b.depositUnsettled)
          const Padding(
            padding: EdgeInsets.only(top: 8),
            child: NoticeBanner(
              message: "Avans hal qilinmagan: qaytaring yoki ushlab qoling.",
            ),
          ),
        for (final PosBookingPayment p in _b.payments) ...<Widget>[
          const Divider(height: 20),
          Row(
            children: <Widget>[
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      '${tenderLabel(p.tender)} · ${p.amount.format()}',
                      style: RzType.body.copyWith(color: c.fg),
                    ),
                    Text(
                      <String>[
                        p.statusLabel ?? p.status,
                        if (p.createdAt != null)
                          '${dm(p.createdAt!)} ${hm(p.createdAt!)}',
                        if (p.remaining.isPositive)
                          'qoldiq ${p.remaining.formatAmount()}',
                      ].join(' · '),
                      style: RzType.footnote.copyWith(color: c.fgMuted),
                    ),
                  ],
                ),
              ),
              if (p.remaining.isPositive && _b.status != 'arrived')
                TextButton(
                  onPressed: _busy ? null : () => _settle(p, refund: true),
                  child: const Text('Qaytarish'),
                ),
              if (p.remaining.isPositive &&
                  const <String>[
                    'canceled',
                    'no_show',
                    'completed',
                  ].contains(_b.status))
                TextButton(
                  onPressed: _busy ? null : () => _settle(p, refund: false),
                  child: const Text('Ushlab qolish'),
                ),
            ],
          ),
        ],
      ],
    ),
  );
}

/// Avans olish: naqd, karta yoki QR; bo'nak cheki chiqadi. `Idempotency-Key` oyna ochilganda yaratiladi: aloqa
/// uzilsa xuddi shu avans qayta yuboriladi (ikkinchi chek chiqmaydi).
class _DepositDialog extends StatefulWidget {
  const _DepositDialog({required this.booking, this.room});

  final PosBooking booking;
  final String? room;

  @override
  State<_DepositDialog> createState() => _DepositDialogState();
}

class _DepositDialogState extends State<_DepositDialog> {
  final TenderDraft _draft = TenderDraft();
  String? _error;
  bool _busy = false;
  String? _key;

  Future<void> _submit() async {
    final String? error = _draft.validate();
    setState(() => _error = error);
    if (error != null) return;
    setState(() => _busy = true);
    final PosScope scope = PosScope.of(context);
    _key ??= scope.data.http.newKey();
    try {
      final PosDepositResult r = await scope.data.bookings.takeDeposit(
        widget.booking.uuid,
        amount: _draft.amount!,
        tender: _draft.tender.wire,
        cardType: _draft.tender == PosTender.card ? _draft.cardType : null,
        transactionId: _draft.tender == PosTender.card
            ? _draft.transactionId.trim()
            : null,
        idempotencyKey: _key,
      );
      final PosFiscalReceiptDetail? detail = await receiptOf(
        scope.data,
        r.fiscal,
      );
      if (!mounted) return;
      final ReceiptPrinter printer = ReceiptPrinter(scope);
      final FiscalReceipt doc = advanceReceiptOf(
        booking: r.booking,
        payment: r.payment,
        detail: detail,
        header: printer.header,
        now: scope.services.now(),
        room: widget.room,
        cashier: printer.cashier,
      );
      await printer.print(
        context,
        (PaperWidth paper) => layoutFiscalReceipt(doc, paper),
        openDrawer: _draft.tender == PosTender.cash,
      );
      if (!mounted) return;
      showPosMessage(
        context,
        "Avans qabul qilindi: ${r.payment.amount.format()}. Bo'nak cheki chop etildi.",
      );
      Navigator.pop(context, r);
    } on PosNetworkException {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = "Aloqa yo'q: avans holati noma'lum. Qayta yuborish xavfsiz — ikki marta yozilmaydi.";
        });
      }
    } on Object catch (e) {
      // Boshqa xato: so'rov rad etildi — keyingisi yangi amal
      _key = null;
      if (mounted) {
        setState(() => _busy = false);
        showPosError(context, e);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return AlertDialog(
      title: const Text('Avans olish'),
      content: SizedBox(
        width: 480,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                "Bo'nak cheki chiqadi (QR va fiskal belgisiz). Yopishda to'liq summaga sotuv cheki chiqadi, avans undan "
                'ayiriladi.',
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 16),
              TenderForm(
                draft: _draft,
                amountLabel: 'Avans summasi',
                error: _error,
                onChanged: () => setState(() => _error = null),
              ),
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
          key: const ValueKey<String>('deposit-submit'),
          label: 'Avansni olish',
          loading: _busy,
          onPressed: _submit,
        ),
      ],
    );
  }
}
