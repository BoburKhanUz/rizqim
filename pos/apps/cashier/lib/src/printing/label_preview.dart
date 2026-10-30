import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../features/billing/cashier_ui.dart';
import 'label_printing.dart';

/// Yorliqning ekrandagi ko'rinishi (qog'oz nisbatida, katak to'ri bo'yicha): kassir stikerni chop etishdan oldin yoki
/// printer yo'q paytda ko'radi.
class LabelPreview extends StatelessWidget {
  const LabelPreview({super.key, required this.layout, this.width = 200});

  final LabelLayout layout;
  final double width;

  @override
  Widget build(BuildContext context) {
    final LabelSize size = layout.size;
    final double height = width * size.heightMm / size.widthMm;
    final double scale = width / size.dotsWidth;
    final double cell = LabelSize.cellWidth * scale;
    final double row = LabelSize.rowHeight * scale;
    final double margin = LabelSize.margin * scale;
    const Color ink = Color(0xFF111111);
    const Color paper = Color(0xFFFFFFFF);
    return Semantics(
      label: 'Stiker: ${layout.texts.map((LabelText t) => t.text).join(', ')}',
      excludeSemantics: true,
      child: Container(
        width: width,
        height: height,
        decoration: BoxDecoration(
          color: paper,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: const Color(0xFFBDBDBD)),
        ),
        child: Stack(
          clipBehavior: Clip.hardEdge,
          children: <Widget>[
            for (final LabelBar b in layout.bars)
              Positioned(
                left: margin,
                right: margin,
                top: margin + b.row * row + row / 2 - 1,
                height: 2,
                child: const ColoredBox(color: ink),
              ),
            for (final LabelText t in layout.texts)
              Positioned(
                left: margin + t.col * cell,
                top: margin + t.row * row,
                width: t.cells * cell + 1,
                height: t.rowSpan * row,
                child: ColoredBox(
                  color: t.inverted ? ink : const Color(0x00000000),
                  child: FittedBox(
                    fit: BoxFit.fill,
                    alignment: Alignment.centerLeft,
                    child: Text(
                      t.text,
                      maxLines: 1,
                      softWrap: false,
                      style: TextStyle(
                        fontSize: row * 0.8,
                        height: 1,
                        color: t.inverted ? paper : ink,
                        fontWeight: t.bold ? FontWeight.w800 : FontWeight.w500,
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// "Stikerlar" oynasi: buyurtmaning stikerlari va paket yorlig'i ko'rinishda, "Chop etish" (qayta chop etish ham).
/// Printer bo'lmasa — oddiy izoh.
Future<void> showLabelSheet(
  BuildContext context, {
  required CashierLabels labels,
  required LabelBatch batch,
}) => showModalBottomSheet<void>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  builder: (BuildContext sheet) => ConstrainedBox(
    constraints: BoxConstraints(
      maxHeight: MediaQuery.sizeOf(sheet).height * 0.85,
    ),
    child: LabelSheet(labels: labels, batch: batch),
  ),
);

class LabelSheet extends StatelessWidget {
  const LabelSheet({super.key, required this.labels, required this.batch});

  final CashierLabels labels;
  final LabelBatch batch;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final List<LabelLayout> layouts = batch.layouts;
    final bool printer = labels.printer != null;
    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
            child: Text(
              'Stikerlar (${layouts.length})',
              style: RzType.title.copyWith(color: c.fg),
            ),
          ),
          Flexible(
            child: layouts.isEmpty
                ? Padding(
                    padding: const EdgeInsets.all(20),
                    child: Text(
                      "Bu buyurtmada stiker chiqadigan taom yo'q.",
                      style: RzType.body.copyWith(color: c.fgMuted),
                    ),
                  )
                : SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
                    child: Wrap(
                      spacing: 12,
                      runSpacing: 12,
                      children: <Widget>[
                        for (var i = 0; i < layouts.length; i++)
                          LabelPreview(
                            key: ValueKey<String>('label-preview-$i'),
                            layout: layouts[i],
                            width: batch.size == LabelSize.mm58x40 ? 232 : 200,
                          ),
                      ],
                    ),
                  ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 16),
            child: printer
                ? RzButton(
                    key: const ValueKey<String>('labels-print'),
                    label: 'Chop etish',
                    icon: Icons.print_outlined,
                    size: RzButtonSize.lg,
                    fullWidth: true,
                    onPressed: layouts.isEmpty
                        ? null
                        : () {
                            unawaited(labels.print(batch));
                            showPosMessage(
                              context,
                              'Stikerlar navbatga qo\'yildi: ${layouts.length} ta.',
                            );
                            Navigator.of(context).pop();
                          },
                  )
                : Text(
                    "Stiker printeri sozlanmagan: boshqaruv panelida Printerlar bo'limida «Yorliq» printerini qo'shing.",
                    style: RzType.footnote.copyWith(color: c.fgMuted),
                  ),
          ),
        ],
      ),
    );
  }
}
