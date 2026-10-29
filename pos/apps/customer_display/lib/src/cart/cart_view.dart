import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../qr/qr_view.dart';
import '../widgets/display_type.dart';

/// To'lov usulining mijozga tushunarli nomi; noma'lum usulda `null` (ko'rsatilmaydi).
String? paymentMethodLabel(String? method) => switch (method) {
  'cash' => 'Naqd pul',
  'card' => 'Karta',
  'terminal' => 'Bank terminali',
  'qr' || 'click' || 'payme' || 'uzum' => 'QR orqali',
  _ => null,
};

/// Kassaning ikkinchi ekrani: joriy chek (chapda), jami summa va to'lov (o'ngda). Tor ekranda ustma-ust. "Rahmat"
/// ([PosDisplayScreen.paid]) alohida ekran.
class CartView extends StatelessWidget {
  const CartView({super.key, required this.cart, this.storeName});

  final PosDisplayCart cart;
  final String? storeName;

  @override
  Widget build(BuildContext context) {
    if (cart.state == PosDisplayScreen.paid) {
      return PaidView(cart: cart);
    }
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints box) {
        final bool wide = box.maxWidth >= 760 && box.maxWidth > box.maxHeight * 0.9;
        final bool showsQr = cart.state == PosDisplayScreen.payment && cart.payment?.qr != null;
        final Widget lines = _LinesPanel(cart: cart, storeName: storeName);
        final Widget summary = _SummaryPanel(cart: cart);
        return Padding(
          padding: const EdgeInsets.all(24),
          child: wide
              ? Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Expanded(flex: 3, child: lines),
                    const SizedBox(width: 24),
                    Expanded(flex: 2, child: summary),
                  ],
                )
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Expanded(flex: showsQr ? 2 : 3, child: lines),
                    const SizedBox(height: 16),
                    Expanded(flex: showsQr ? 3 : 2, child: summary),
                  ],
                ),
        );
      },
    );
  }
}

class _LinesPanel extends StatefulWidget {
  const _LinesPanel({required this.cart, this.storeName});

  final PosDisplayCart cart;
  final String? storeName;

  @override
  State<_LinesPanel> createState() => _LinesPanelState();
}

class _LinesPanelState extends State<_LinesPanel> {
  final ScrollController _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    // Ekran chek o'rtasida ochilsa ham oxirgi qo'shilgan taom ko'rinadi
    if (widget.cart.lines.length > 1) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _scroll.hasClients) {
          _scroll.jumpTo(_scroll.position.maxScrollExtent);
        }
      });
    }
  }

  @override
  void didUpdateWidget(_LinesPanel old) {
    super.didUpdateWidget(old);
    // Yangi taom qo'shilsa, ro'yxat pastiga siljiydi: mijoz oxirgi qo'shilganini ko'radi
    if (widget.cart.lines.length > old.cart.lines.length) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted || !_scroll.hasClients) return;
        _scroll.animateTo(
          _scroll.position.maxScrollExtent,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      });
    }
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosDisplayCart cart = widget.cart;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: c.surface,
        borderRadius: BorderRadius.circular(RzRadius.sheet),
        border: Border.all(color: c.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(28, 22, 28, 12),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    widget.storeName ?? 'Buyurtmangiz',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: DisplayType.heading.copyWith(color: c.fgMuted),
                  ),
                ),
                if (cart.queueNumber != null)
                  DecoratedBox(
                    key: const ValueKey<String>('cart-queue-number'),
                    decoration: BoxDecoration(
                      color: c.brandSoft,
                      borderRadius: BorderRadius.circular(RzRadius.control),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 6),
                      child: Text(
                        'Raqam ${cart.queueNumber}',
                        style: DisplayType.heading.copyWith(color: c.brandSoftFg),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          Divider(height: 1, color: c.line),
          Expanded(
            child: cart.lines.isEmpty
                ? Center(
                    child: Text(
                      "Taomlar qo'shilmoqda…",
                      style: DisplayType.label.copyWith(color: c.fgSubtle),
                    ),
                  )
                : ListView.separated(
                    controller: _scroll,
                    padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 8),
                    itemCount: cart.lines.length,
                    separatorBuilder: (_, _) => Divider(height: 1, color: c.line),
                    itemBuilder: (BuildContext context, int i) =>
                        _LineRow(line: cart.lines[i]),
                  ),
          ),
        ],
      ),
    );
  }
}

class _LineRow extends StatelessWidget {
  const _LineRow({required this.line});

  final PosDisplayLine line;

  String get _quantity {
    final String q = line.quantity.format();
    final String? unit = line.unit;
    if (unit != null && unit.isNotEmpty && unit != 'piece' && unit != 'pcs') {
      return '$q $unit';
    }
    return '$q ×';
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints box) {
        // Telefon kengligida (tor ekran) shrift va ustun kichraytiriladi
        final bool narrow = box.maxWidth < 520;
        final TextStyle name = (narrow ? DisplayType.modifier : DisplayType.line).copyWith(color: c.fg);
        final TextStyle amount = (narrow ? DisplayType.modifier : DisplayType.lineAmount).copyWith(
          fontFeatures: const <FontFeature>[FontFeature.tabularFigures()],
          fontWeight: FontWeight.w700,
          color: c.fg,
        );
        return Semantics(
          container: true,
          label: '${line.name}, ${_quantity.replaceAll('×', 'dona')}, ${line.total.format()}',
          child: Padding(
            padding: EdgeInsets.symmetric(vertical: narrow ? 10 : 14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                SizedBox(
                  width: narrow ? 64 : 110,
                  child: Text(
                    _quantity,
                    style: amount.copyWith(color: c.brandSoftFg),
                  ),
                ),
                Expanded(
                  flex: 3,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(line.name, style: name),
                      for (final String m in line.modifiers)
                        Text('+ $m', style: DisplayType.modifier.copyWith(color: c.fgMuted, fontSize: narrow ? 16 : null)),
                      if (line.note != null && line.note!.isNotEmpty)
                        Text(
                          line.note!,
                          style: DisplayType.modifier.copyWith(
                            color: c.fgMuted,
                            fontStyle: FontStyle.italic,
                            fontSize: narrow ? 16 : null,
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                // Uzun summa qatorga sig'masa kichrayadi (kam kengliklarda ham kesilmaydi)
                Flexible(
                  flex: 2,
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerRight,
                    child: Text(line.total.format(), style: amount),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _SummaryPanel extends StatelessWidget {
  const _SummaryPanel({required this.cart});

  final PosDisplayCart cart;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool paying = cart.state == PosDisplayScreen.payment;
    final PosDisplayPayment? payment = cart.payment;
    final PosDisplayQr? qr = paying ? payment?.qr : null;
    final String? method = paymentMethodLabel(payment?.method);
    return DecoratedBox(
      decoration: BoxDecoration(
        color: paying ? c.brandSoft : c.surface,
        borderRadius: BorderRadius.circular(RzRadius.sheet),
        border: Border.all(color: paying ? c.brand : c.line, width: paying ? 2 : 1),
      ),
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            if (cart.discount.isPositive) ...<Widget>[
              _Row(label: 'Jami', value: cart.subtotal.format(), color: c.fgMuted),
              const SizedBox(height: 6),
              _Row(
                label: 'Chegirma',
                value: '-${cart.discount.format()}',
                color: c.successSoftFg,
              ),
              const SizedBox(height: 16),
            ],
            Text(
              paying ? "To'lash kerak" : 'Jami',
              style: DisplayType.label.copyWith(color: paying ? c.brandSoftFg : c.fgMuted),
            ),
            const SizedBox(height: 4),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                cart.total.format(),
                key: const ValueKey<String>('cart-total'),
                maxLines: 1,
                style: DisplayType.total.copyWith(color: c.fg),
              ),
            ),
            if (paying && method != null) ...<Widget>[
              const SizedBox(height: 8),
              Row(
                children: <Widget>[
                  Icon(_methodIcon(payment?.method), color: c.brandSoftFg, size: 30),
                  const SizedBox(width: 10),
                  Text(method, style: DisplayType.label.copyWith(color: c.brandSoftFg)),
                ],
              ),
            ],
            if (paying && payment?.tendered != null) ...<Widget>[
              const SizedBox(height: 12),
              _Row(label: 'Berildi', value: payment!.tendered!.format(), color: c.fgMuted),
            ],
            if (paying && payment?.change != null && payment!.change!.isPositive) ...<Widget>[
              const SizedBox(height: 6),
              _Row(label: 'Qaytim', value: payment.change!.format(), color: c.fg),
            ],
            if (qr != null)
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: LayoutBuilder(
                    builder: (BuildContext context, BoxConstraints box) {
                      // QR eng katta kvadrat: yozuv uchun joy qoldirib, bor kenglik va balandlikka sig'adi
                      const double captionHeight = 72;
                      final double side = (box.maxHeight - captionHeight < box.maxWidth
                              ? box.maxHeight - captionHeight
                              : box.maxWidth)
                          .clamp(96, 440)
                          .toDouble();
                      return Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: <Widget>[
                          QrView(payload: qr.payload, size: side),
                          const SizedBox(height: 8),
                          Text(
                            qr.label != null
                                ? "${qr.label} orqali to'lash uchun skanerlang"
                                : "To'lash uchun telefoningiz kamerasi bilan skanerlang",
                            textAlign: TextAlign.center,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: DisplayType.modifier.copyWith(color: c.brandSoftFg),
                          ),
                        ],
                      );
                    },
                  ),
                ),
              )
            else if (!paying)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(
                  "Taomlar qo'shilmoqda…",
                  style: DisplayType.modifier.copyWith(color: c.fgSubtle),
                ),
              ),
          ],
        ),
      ),
    );
  }

  static IconData _methodIcon(String? method) => switch (method) {
    'cash' => Icons.payments_outlined,
    'card' || 'terminal' => Icons.credit_card_rounded,
    _ => Icons.qr_code_2_rounded,
  };
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value, required this.color});

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) => Row(
    children: <Widget>[
      Expanded(child: Text(label, style: DisplayType.label.copyWith(color: color))),
      Text(value, style: DisplayType.lineAmount.copyWith(color: color)),
    ],
  );
}

/// To'landi: "Rahmat!", summa, qaytim va (bo'lsa) navbat raqami.
class PaidView extends StatelessWidget {
  const PaidView({super.key, required this.cart});

  final PosDisplayCart cart;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosMoney? change = cart.payment?.change;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            DecoratedBox(
              decoration: BoxDecoration(color: c.successSoft, shape: BoxShape.circle),
              child: Padding(
                padding: const EdgeInsets.all(28),
                child: Icon(Icons.check_rounded, size: 96, color: c.successSoftFg),
              ),
            ),
            const SizedBox(height: 24),
            Text(
              'Rahmat!',
              key: const ValueKey<String>('paid-thanks'),
              style: DisplayType.thanks.copyWith(color: c.fg),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              "To'landi: ${cart.total.format()}",
              style: DisplayType.heading.copyWith(color: c.fgMuted),
              textAlign: TextAlign.center,
            ),
            if (change != null && change.isPositive) ...<Widget>[
              const SizedBox(height: 8),
              Text(
                'Qaytim: ${change.format()}',
                style: DisplayType.heading.copyWith(color: c.fg),
                textAlign: TextAlign.center,
              ),
            ],
            if (cart.queueNumber != null) ...<Widget>[
              const SizedBox(height: 28),
              DecoratedBox(
                key: const ValueKey<String>('paid-queue-number'),
                decoration: BoxDecoration(
                  color: c.brandSoft,
                  borderRadius: BorderRadius.circular(RzRadius.sheet),
                ),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 40, vertical: 16),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      Text(
                        'Raqamingiz',
                        style: DisplayType.label.copyWith(color: c.brandSoftFg),
                      ),
                      Text(
                        '${cart.queueNumber}',
                        style: DisplayType.number(96).copyWith(color: c.brandSoftFg),
                      ),
                      Text(
                        'Raqamingiz ekranda chiqqach oling',
                        style: DisplayType.modifier.copyWith(color: c.brandSoftFg),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
