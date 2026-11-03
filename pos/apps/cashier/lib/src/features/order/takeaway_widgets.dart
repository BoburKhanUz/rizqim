import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../billing/cashier_ui.dart';
import 'order_format.dart';

/// P6-34 qadoq qatorlari (savat, buyurtma, schyot): har biri o'z narxi bilan; "Olib tashlash" (sabab bilan, mijoz o'z
/// paketini olib keldi) va "Qaytarish". Internetsiz — qatorlar ko'rinadi, tugmalar o'chiq va oddiy izoh.
class PackagingLinesView extends StatelessWidget {
  const PackagingLinesView({
    super.key,
    required this.lines,
    this.onRemove,
    this.onRestore,
    this.locked = false,
    this.estimate = false,
  });

  final List<PosPackagingLine> lines;
  final ValueChanged<PosPackagingLine>? onRemove;
  final ValueChanged<PosPackagingLine>? onRestore;

  /// Internetsiz: o'zgartirib bo'lmaydi.
  final bool locked;

  /// Savatdagi taxminiy hisob (buyurtma hali ochilmagan).
  final bool estimate;

  @override
  Widget build(BuildContext context) {
    if (lines.isEmpty) return const SizedBox.shrink();
    final RzColors c = context.rz;
    return Column(
      key: const ValueKey<String>('packaging-lines'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        for (final PosPackagingLine l in lines)
          Padding(
            padding: const EdgeInsets.only(bottom: 4),
            child: Row(
              children: <Widget>[
                Icon(
                  Icons.shopping_bag_outlined,
                  size: 20,
                  color: l.removed ? c.fgSubtle : c.fgMuted,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        '${l.name}${l.quantity > 1 ? ' × ${l.quantity}' : ''}',
                        style: RzType.body.copyWith(
                          color: l.removed ? c.fgSubtle : c.fg,
                          decoration: l.removed
                              ? TextDecoration.lineThrough
                              : null,
                        ),
                      ),
                      if (l.removed)
                        Text(
                          'Olib tashlandi${(l.removedReason ?? '').isEmpty ? '' : ': ${l.removedReason}'}',
                          style: RzType.footnote.copyWith(color: c.fgMuted),
                        ),
                      if (l.removed ? onRestore != null : onRemove != null)
                        TextButton(
                          key: ValueKey<String>(
                            'packaging-${l.removed ? 'restore' : 'remove'}-${l.ruleId}',
                          ),
                          style: TextButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 4),
                            minimumSize: const Size(44, 36),
                            alignment: Alignment.centerLeft,
                          ),
                          onPressed: locked
                              ? null
                              : () => l.removed ? onRestore!(l) : onRemove!(l),
                          child: Text(
                            l.removed ? 'Qaytarish' : 'Olib tashlash',
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  l.removed
                      ? PosMoney.zero.format()
                      : (l.unitPrice * l.quantity).format(),
                  style: RzType.body.copyWith(
                    color: l.removed ? c.fgSubtle : c.fg,
                  ),
                ),
              ],
            ),
          ),
        if (locked && (onRemove != null || onRestore != null))
          Text(
            "Internet yo'q: qadoqni olib tashlab bo'lmaydi.",
            key: const ValueKey<String>('packaging-offline'),
            style: RzType.footnote.copyWith(color: c.fgMuted),
          )
        else if (estimate)
          Text(
            'Qadoq olib ketishga avtomatik qo\'shiladi.',
            style: RzType.footnote.copyWith(color: c.fgMuted),
          ),
      ],
    );
  }
}

/// "Olib tashlash" sababi (mijoz o'z paketini olib keldi); bekor qilinsa `null`.
Future<String?> askPackagingReason(BuildContext context, PosPackagingLine l) =>
    askReason(
      context,
      title: '«${l.name}» ni olib tashlash',
      message: "Qadoq chekdan olinadi. Sabab auditda yoziladi.",
      presets: const <String>[
        "Mijoz o'z paketini olib keldi",
        'Paket kerak emas',
      ],
      confirmLabel: 'Olib tashlash',
    );

/// Olib ketish vaqti tanlovi: vaqt yoki "Hozir" (vaqtni olib tashlash).
class PickupPick {
  const PickupPick(this.slot);

  /// `null` — hozir (vaqt olib tashlanadi).
  final PosPickupSlot? slot;
}

/// "Vaqtga" oynasi: filial taklif qiladigan vaqtlar (`GET /pos/pickup-slots`), bir bosishda. [current] bo'lsa "Hozir"
/// tugmasi vaqtni olib tashlaydi.
Future<PickupPick?> showPickupSlots(
  BuildContext context, {
  required Future<List<PosPickupSlot>> Function() load,
  String? current,
}) => showModalBottomSheet<PickupPick>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  builder: (BuildContext sheet) => ConstrainedBox(
    constraints: BoxConstraints(
      maxHeight: MediaQuery.sizeOf(sheet).height * 0.8,
    ),
    child: _PickupSlotsSheet(load: load, current: current),
  ),
);

class _PickupSlotsSheet extends StatefulWidget {
  const _PickupSlotsSheet({required this.load, this.current});

  final Future<List<PosPickupSlot>> Function() load;
  final String? current;

  @override
  State<_PickupSlotsSheet> createState() => _PickupSlotsSheetState();
}

class _PickupSlotsSheetState extends State<_PickupSlotsSheet> {
  List<PosPickupSlot>? _slots;
  Object? _error;

  @override
  void initState() {
    super.initState();
    unawaited(_fetch());
  }

  Future<void> _fetch() async {
    setState(() => _error = null);
    try {
      final List<PosPickupSlot> slots = await widget.load();
      if (mounted) setState(() => _slots = slots);
    } on PosException catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final List<PosPickupSlot>? slots = _slots;
    final Map<String, List<PosPickupSlot>> byDay =
        <String, List<PosPickupSlot>>{};
    for (final PosPickupSlot s in slots ?? const <PosPickupSlot>[]) {
      (byDay[s.businessDate] ??= <PosPickupSlot>[]).add(s);
    }
    final List<String> days = byDay.keys.toList();
    return SafeArea(
      child: ListView(
        shrinkWrap: true,
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
        children: <Widget>[
          Text('Olib ketish vaqti', style: RzType.title.copyWith(color: c.fg)),
          const SizedBox(height: 4),
          Text(
            "Oshxona buyurtmani vaqtidan oldin tayyorlaydi; ungacha taomlar kutib turadi.",
            style: RzType.footnote.copyWith(color: c.fgMuted),
          ),
          const SizedBox(height: 12),
          if (widget.current != null)
            Align(
              alignment: Alignment.centerLeft,
              child: OutlinedButton.icon(
                key: const ValueKey<String>('pickup-now'),
                onPressed: () =>
                    Navigator.of(context).pop(const PickupPick(null)),
                icon: const Icon(Icons.flash_on_rounded),
                label: const Text('Hozir (vaqtsiz)'),
              ),
            ),
          if (_error != null)
            InlineError(
              message: posErrorText(_error!),
              onRetry: () => unawaited(_fetch()),
            )
          else if (slots == null)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (slots.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Text(
                "Bugun bo'sh vaqt qolmadi.",
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
            )
          else
            for (final String day in days) ...<Widget>[
              if (days.length > 1)
                Padding(
                  padding: const EdgeInsets.only(top: 12, bottom: 6),
                  child: Text(
                    day == days.first ? 'Bugun' : 'Ertaga',
                    style: RzType.headline.copyWith(color: c.fg),
                  ),
                ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  for (final PosPickupSlot s in byDay[day]!)
                    OutlinedButton(
                      key: ValueKey<String>('pickup-slot-${s.time}'),
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size(88, 48),
                      ),
                      onPressed: () => Navigator.of(context).pop(PickupPick(s)),
                      child: Text(s.time, style: RzType.headline),
                    ),
                ],
              ),
            ],
        ],
      ),
    );
  }
}

/// "Vaqtga" tugmasi yoki tanlangan vaqt ("Olib ketish: 13:30", bosilsa — o'zgartirish).
class PickupTimeButton extends StatelessWidget {
  const PickupTimeButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.waiting = true,
  });

  /// Tanlangan vaqt (`HH:mm`); `null` — hali tanlanmagan.
  final String? label;
  final VoidCallback? onPressed;

  /// Oshxonaga hali chiqmagan (vaqtni o'zgartirish mumkin).
  final bool waiting;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    if (label == null) {
      return OutlinedButton.icon(
        key: const ValueKey<String>('pickup-open'),
        onPressed: onPressed,
        icon: const Icon(Icons.schedule_rounded),
        label: const Text('Vaqtga'),
      );
    }
    return Material(
      color: Color.alphaBlend(c.infoSoft, c.surface),
      borderRadius: BorderRadius.circular(RzRadius.control),
      child: InkWell(
        key: const ValueKey<String>('pickup-open'),
        borderRadius: BorderRadius.circular(RzRadius.control),
        onTap: waiting ? onPressed : null,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(Icons.schedule_rounded, color: c.infoSoftFg, size: 20),
              const SizedBox(width: 8),
              Flexible(
                child: Text(
                  'Olib ketish: $label',
                  key: const ValueKey<String>('pickup-label'),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: RzType.headline.copyWith(color: c.infoSoftFg),
                ),
              ),
              if (!waiting) ...<Widget>[
                const SizedBox(width: 8),
                Text(
                  'oshxonada',
                  style: RzType.footnote.copyWith(color: c.infoSoftFg),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Olib ketish vaqti do'kon vaqtida (talon va paket yorlig'i uchun): serverning `local_time` i ("dd.MM HH:mm") dan,
/// bo'lmasa qurilma vaqtida.
DateTime pickupLocalTime(PosPickupTime p) {
  final RegExpMatch? m = RegExp(r'^(\d{2})\.(\d{2}) (\d{2}):(\d{2})$')
      .firstMatch(p.localTime.trim());
  if (m == null) return p.pickupAt.toLocal();
  final DateTime utc = p.pickupAt.toUtc();
  final int month = int.parse(m[2]!);
  // 31.12 evening in UTC can already be 01.01 in the store
  final int year = month == 1 && utc.month == 12 ? utc.year + 1 : utc.year;
  return DateTime(
    year,
    month,
    int.parse(m[1]!),
    int.parse(m[3]!),
    int.parse(m[4]!),
  );
}

/// Buyurtmaning olib ketish vaqti `HH:mm` (serverning do'kon vaqtidagi `local_time` i "dd.MM HH:mm" dan).
String? pickupTimeLabel(PosPickupTime? p) {
  if (p == null) return null;
  final String t = p.localTime.trim();
  if (t.isEmpty) return formatReceiptTime(p.pickupAt.toLocal());
  final int space = t.lastIndexOf(' ');
  return space < 0 ? t : t.substring(space + 1);
}
