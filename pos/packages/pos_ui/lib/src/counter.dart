import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// P6-12: tez kassa komponentlari — tez miqdor (×2…×10), savat paneli (telefonda pastda), navbat raqami kartasi
/// (talon ekranda) va "Tayyor" ro'yxatining qatori.

/// Tez miqdor: keyingi bosishdagi taom soni (×1 — oddiy bosish). Tanlangani ajralib turadi.
class PosQuickQuantityBar extends StatelessWidget {
  const PosQuickQuantityBar({
    super.key,
    required this.value,
    required this.onChanged,
    this.options = const <int>[2, 3, 4, 5, 10],
    this.compact = false,
  });

  final int value;
  final ValueChanged<int> onChanged;

  /// Ko'rsatiladigan ko'paytmalar (×1 doim birinchi).
  final List<int> options;

  /// Telefon: kichikroq tugmalar.
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final double h = compact ? RzTouch.min : RzTouch.large;
    Widget chip(int n) {
      final bool selected = value == n;
      return Padding(
        padding: const EdgeInsets.only(right: 6),
        child: Semantics(
          button: true,
          selected: selected,
          label: n == 1 ? 'Miqdor 1' : 'Miqdor $n',
          excludeSemantics: true,
          child: SizedBox(
            height: h,
            child: FilledButton(
              key: ValueKey<String>('qty-x$n'),
              onPressed: () => onChanged(selected && n != 1 ? 1 : n),
              style: FilledButton.styleFrom(
                minimumSize: Size(compact ? 48 : 60, h),
                padding: const EdgeInsets.symmetric(horizontal: 10),
                backgroundColor: selected
                    ? c.brand
                    : Color.alphaBlend(c.fg.withValues(alpha: 0.06), c.surface),
                foregroundColor: selected ? c.onBrand : c.fg,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(RzRadius.control),
                ),
                textStyle: compact ? RzType.headline : RzType.title,
              ),
              child: Text('×$n'),
            ),
          ),
        ),
      );
    }

    return SizedBox(
      height: h,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: <Widget>[
          chip(1),
          for (final int n in options)
            if (n != 1) chip(n),
        ],
      ),
    );
  }
}

/// Telefondagi pastki savat paneli: taomlar soni va jami, bosilsa savat ochiladi; "To'lash" — katta tugma.
class PosCartBar extends StatelessWidget {
  const PosCartBar({
    super.key,
    required this.count,
    required this.total,
    required this.onOpen,
    required this.onPay,
    this.payLabel = "To'lash",
    this.busy = false,
    this.number,
  });

  final int count;
  final PosMoney total;
  final VoidCallback? onOpen;
  final VoidCallback? onPay;
  final String payLabel;
  final bool busy;

  /// Olingan navbat raqami (bo'lsa).
  final String? number;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Material(
      color: c.surface,
      shape: Border(top: BorderSide(color: c.line)),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
          child: Row(
            children: <Widget>[
              Expanded(
                child: Semantics(
                  button: onOpen != null,
                  label: 'Savat: $count ta, ${total.format()}',
                  excludeSemantics: true,
                  child: InkWell(
                    key: const ValueKey<String>('cart-bar'),
                    onTap: onOpen,
                    borderRadius: BorderRadius.circular(RzRadius.control),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 4,
                        vertical: 6,
                      ),
                      child: Row(
                        children: <Widget>[
                          Badge(
                            isLabelVisible: count > 0,
                            label: Text('$count'),
                            child: Icon(
                              Icons.shopping_basket_outlined,
                              size: 28,
                              color: c.fg,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(
                                  total.format(),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: RzType.title.copyWith(color: c.fg),
                                ),
                                Text(
                                  number == null
                                      ? (count == 0
                                            ? "Savat bo'sh"
                                            : 'Savatni ochish')
                                      : 'Raqam $number',
                                  style: RzType.footnote.copyWith(
                                    color: c.fgMuted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              RzButton(
                key: const ValueKey<String>('counter-pay'),
                label: payLabel,
                icon: Icons.payments_outlined,
                size: RzButtonSize.lg,
                loading: busy,
                onPressed: onPay,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Navbat raqami kartasi: "Navbat raqamingiz", katta raqam, "Olib ketish" / "Zalda" va izoh (ekrandagi talon).
class PosQueueNumberCard extends StatelessWidget {
  const PosQueueNumberCard({
    super.key,
    required this.number,
    this.label = 'Navbat raqami',
    this.orderLabel,
    this.message,
    this.tone = PosQueueTone.brand,
    this.dense = false,
  });

  final String number;
  final String label;
  final String? orderLabel;
  final String? message;
  final PosQueueTone tone;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final (Color bg, Color fg) = switch (tone) {
      PosQueueTone.brand => (
        Color.alphaBlend(c.brandSoft, c.surface),
        c.brandSoftFg,
      ),
      PosQueueTone.ready => (
        Color.alphaBlend(c.successSoft, c.surface),
        c.successSoftFg,
      ),
      PosQueueTone.muted => (c.surface2, c.fg),
    };
    return Semantics(
      label: '$label $number${orderLabel == null ? '' : ', $orderLabel'}',
      excludeSemantics: true,
      child: Container(
        padding: EdgeInsets.symmetric(
          horizontal: 20,
          vertical: dense ? 10 : 18,
        ),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(RzRadius.card),
          border: Border.all(color: fg.withValues(alpha: 0.25)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(label, style: RzType.headline.copyWith(color: fg)),
            FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(
                number,
                key: const ValueKey<String>('queue-number'),
                style: RzType.display.copyWith(
                  color: fg,
                  fontSize: dense ? 44 : 84,
                  height: 1.05,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            if (orderLabel != null)
              Text(orderLabel!, style: RzType.title.copyWith(color: fg)),
            if (message != null) ...<Widget>[
              const SizedBox(height: 6),
              Text(
                message!,
                textAlign: TextAlign.center,
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

enum PosQueueTone { brand, ready, muted }

/// "Tayyor" ro'yxatidagi raqam: raqam, kutish vaqti, "Berildi" va "Qayta chaqirish" (yoki tayyorlanayotganda
/// "Tayyor").
class PosReadyNumberTile extends StatelessWidget {
  const PosReadyNumberTile({
    super.key,
    required this.number,
    this.detail,
    this.ready = true,
    this.onHanded,
    this.onCall,
    this.onReady,
    this.busy = false,
  });

  final String number;
  final String? detail;

  /// `false` — hali tayyorlanmoqda ("Tayyor" tugmasi, KDS'siz kassa).
  final bool ready;
  final VoidCallback? onHanded;
  final VoidCallback? onCall;
  final VoidCallback? onReady;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final Color accent = ready ? c.success : c.warning;
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
      decoration: BoxDecoration(
        color: c.surface,
        borderRadius: BorderRadius.circular(RzRadius.control),
        border: Border(left: BorderSide(color: accent, width: 4)),
      ),
      child: Row(
        children: <Widget>[
          ConstrainedBox(
            constraints: const BoxConstraints(minWidth: 56),
            child: Text(
              number,
              style: RzType.title.copyWith(
                color: c.fg,
                fontSize: 26,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              detail ?? (ready ? 'Tayyor' : 'Tayyorlanmoqda'),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
          ),
          if (ready && onCall != null)
            IconButton(
              tooltip: 'Qayta chaqirish',
              onPressed: busy ? null : onCall,
              icon: const Icon(Icons.campaign_outlined),
            ),
          if (ready && onHanded != null)
            Padding(
              padding: const EdgeInsets.only(left: 4),
              child: RzButton(
                label: 'Berildi',
                size: RzButtonSize.md,
                variant: RzButtonVariant.secondary,
                onPressed: busy ? null : onHanded,
              ),
            ),
          if (!ready && onReady != null)
            RzButton(
              label: 'Tayyor',
              size: RzButtonSize.md,
              variant: RzButtonVariant.secondary,
              onPressed: busy ? null : onReady,
            ),
        ],
      ),
    );
  }
}
