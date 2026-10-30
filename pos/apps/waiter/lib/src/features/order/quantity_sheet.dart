import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';

import '../../common/sheet.dart';

/// Kg yoki litr (kasr) miqdori: tez tugmalar (0.5, 0.75, 1 ...) va klaviatura; variantning qadami va eng kam
/// miqdoriga tekshiriladi. Yopilsa `null`.
Future<PosQuantity?> showQuantitySheet(
  BuildContext context, {
  required PosProduct product,
  required PosVariant variant,
  PosQuantity? initial,
  bool weighedLater = false,
}) => showWaiterSheet<PosQuantity>(
  context,
  builder: (BuildContext context) => QuantitySheet(
    product: product,
    variant: variant,
    initial: initial,
    weighedLater: weighedLater,
  ),
);

class QuantitySheet extends StatelessWidget {
  const QuantitySheet({
    super.key,
    required this.product,
    required this.variant,
    this.initial,
    this.weighedLater = false,
  });

  final PosProduct product;
  final PosVariant variant;
  final PosQuantity? initial;

  /// P6-25: miqdor taxminiy, haqiqiy og'irlik keyin tortiladi.
  final bool weighedLater;

  @override
  Widget build(BuildContext context) {
    final String unit = variant.unitLabel ?? variant.unit.label;
    // Qadamga to'g'ri kelmaydigan tez tugmalar (masalan 0.7 kg, qadam 0.5) ko'rsatilmaydi
    final List<PosQuantity> presets = <PosQuantity>[
      for (final PosQuantity q in PosQuantity.presets)
        if (variant.accepts(q)) q,
    ];
    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            SheetHeader(
              title: product.name,
              subtitle: weighedLater
                  ? "${variant.price.format()} / $unit · taxminiy miqdor: og'irligi keyin tortiladi"
                  : '${variant.price.format()} / $unit',
            ),
            const SizedBox(height: 12),
            PosQuantityKeypad(
              unit: variant.unit,
              unitLabel: variant.unitLabel,
              initial: initial,
              presets: presets,
              step: variant.step,
              min: variant.minimum,
              keySize: 56,
              onSubmit: (PosQuantity q) => Navigator.of(context).pop(q),
            ),
          ],
        ),
      ),
    );
  }
}
