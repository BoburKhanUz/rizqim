import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../common/sheet.dart';

/// P6-25: og'irlik kutayotgan qatorning haqiqiy og'irligi (kg yoki l, 3 kasrgacha, ≤ 999). Yopilsa `null`.
Future<PosQuantity?> showWeightSheet(
  BuildContext context, {
  required PosOrderItem item,
}) => showWaiterSheet<PosQuantity>(
  context,
  builder: (BuildContext context) => _WeightSheet(item: item),
);

/// Tez tugmalarsiz: tarozi ko'rsatgani teriladi.
const List<PosQuantity> _noPresets = <PosQuantity>[];

class _WeightSheet extends StatelessWidget {
  const _WeightSheet({required this.item});

  final PosOrderItem item;

  @override
  Widget build(BuildContext context) {
    final String basis = item.weightBasis == null
        ? ''
        : ' (${item.weightBasis!.label} og\'irlik)';
    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            SheetHeader(
              title: item.displayName,
              subtitle:
                  "Taxminiy: ${item.quantity.label(item.unit)}. Tarozidagi og'irlikni kiriting$basis.",
            ),
            const SizedBox(height: 12),
            PosQuantityKeypad(
              unit: item.unit,
              presets: _noPresets,
              min: const PosQuantity.milli(1),
              max: const PosQuantity.whole(999),
              keySize: 56,
              submitLabel: 'Saqlash',
              onSubmit: (PosQuantity q) => Navigator.of(context).pop(q),
            ),
          ],
        ),
      ),
    );
  }
}

/// P6-24: stolni kimga berish — filialning ofitsiantlari (buyurtmaning hozirgi ofitsiantisiz) va ixtiyoriy sabab.
/// Yopilsa `null`.
Future<({PosStaffRef waiter, String? reason})?> showTransferSheet(
  BuildContext context, {
  required List<PosStaffRef> waiters,
  required String placeTitle,
}) => showWaiterSheet<({PosStaffRef waiter, String? reason})>(
  context,
  fullHeight: true,
  builder: (BuildContext context) =>
      _TransferSheet(waiters: waiters, placeTitle: placeTitle),
);

class _TransferSheet extends StatefulWidget {
  const _TransferSheet({required this.waiters, required this.placeTitle});

  final List<PosStaffRef> waiters;
  final String placeTitle;

  @override
  State<_TransferSheet> createState() => _TransferSheetState();
}

class _TransferSheetState extends State<_TransferSheet> {
  final TextEditingController _reason = TextEditingController();
  PosStaffRef? _picked;

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return SafeArea(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: SheetHeader(
              title: 'Boshqa ofitsiantga berish',
              subtitle: widget.placeTitle,
            ),
          ),
          Expanded(
            child: widget.waiters.isEmpty
                ? const EmptyState(
                    icon: Icons.person_off_outlined,
                    title: "Boshqa ofitsiant yo'q",
                    message: "Filialda faol ofitsiant topilmadi.",
                  )
                : ListView(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    children: <Widget>[
                      for (final PosStaffRef w in widget.waiters)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 6),
                          child: Material(
                            color: _picked?.id == w.id
                                ? c.brandSoft
                                : Color.alphaBlend(c.surface2, c.surface),
                            borderRadius: BorderRadius.circular(
                              RzRadius.control,
                            ),
                            child: InkWell(
                              borderRadius: BorderRadius.circular(
                                RzRadius.control,
                              ),
                              onTap: () => setState(() => _picked = w),
                              child: ConstrainedBox(
                                constraints: const BoxConstraints(
                                  minHeight: 56,
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 14,
                                  ),
                                  child: Row(
                                    children: <Widget>[
                                      Icon(
                                        _picked?.id == w.id
                                            ? Icons.radio_button_checked
                                            : Icons.radio_button_unchecked,
                                        color: _picked?.id == w.id
                                            ? c.brandSoftFg
                                            : c.fgMuted,
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Text(
                                          w.name,
                                          style: RzType.headline.copyWith(
                                            color: c.fg,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      const SizedBox(height: 8),
                      TextField(
                        controller: _reason,
                        maxLength: 255,
                        textCapitalization: TextCapitalization.sentences,
                        decoration: const InputDecoration(
                          labelText: 'Sabab (ixtiyoriy)',
                          hintText: 'Masalan: smena tugadi',
                          border: OutlineInputBorder(),
                        ),
                      ),
                    ],
                  ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Text(
                  "Bundan keyingi to'lov va choy puli yangi ofitsiantga yoziladi. Ruxsat bo'lmasa menejer PIN'i so'raladi.",
                  style: RzType.footnote.copyWith(color: c.fgMuted),
                ),
                const SizedBox(height: 8),
                RzButton(
                  label: 'Berish',
                  icon: Icons.swap_horiz_rounded,
                  size: RzButtonSize.lg,
                  fullWidth: true,
                  onPressed: _picked == null
                      ? null
                      : () => Navigator.of(context).pop((
                          waiter: _picked!,
                          reason: _reason.text.trim().isEmpty
                              ? null
                              : _reason.text.trim(),
                        )),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
