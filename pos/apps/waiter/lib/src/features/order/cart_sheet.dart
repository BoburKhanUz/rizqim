import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../common/sheet.dart';
import 'draft_cart.dart';
import 'quantity_sheet.dart';

/// Yangi bosqich savati: qatorlar, miqdorni o'zgartirish, izoh, olib tashlash.
Future<void> showCartSheet(BuildContext context, DraftCart cart) =>
    showWaiterSheet<void>(
      context,
      fullHeight: true,
      builder: (BuildContext context) => CartSheet(cart: cart),
    );

class CartSheet extends StatelessWidget {
  const CartSheet({super.key, required this.cart});

  final DraftCart cart;

  Future<void> _editNote(BuildContext context, DraftLine line) async {
    final String? result = await showDialog<String>(
      context: context,
      builder: (BuildContext context) => _NoteDialog(line: line),
    );
    if (result != null) cart.setNote(line, result);
  }

  Future<void> _editMeasured(BuildContext context, DraftLine line) async {
    final PosQuantity? q = await showQuantitySheet(
      context,
      product: line.product,
      variant: line.variant,
      initial: line.quantity,
    );
    if (q != null) cart.setQuantity(line, q);
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return SafeArea(
      child: ListenableBuilder(
        listenable: cart,
        builder: (BuildContext context, _) => Column(
          children: <Widget>[
            const Padding(
              padding: EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Align(
                alignment: Alignment.centerLeft,
                child: SheetHeader(title: 'Yangi bosqich'),
              ),
            ),
            Expanded(
              child: cart.isEmpty
                  ? const EmptyState(
                      icon: Icons.shopping_basket_outlined,
                      title: "Savat bo'sh",
                      message: "Taomlarni ro'yxatdan tanlang.",
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      itemCount: cart.length,
                      separatorBuilder: (_, _) => Divider(color: c.line),
                      itemBuilder: (BuildContext context, int i) {
                        final DraftLine l = cart.lines[i];
                        return _CartRow(
                          line: l,
                          onStep: (PosQuantity q) => cart.setQuantity(l, q),
                          onEditMeasured: () => _editMeasured(context, l),
                          onNote: () => _editNote(context, l),
                          onRemove: () => cart.remove(l),
                        );
                      },
                    ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  if (!cart.isEmpty)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Row(
                        children: <Widget>[
                          Expanded(
                            child: Text(
                              'Taxminiy summa (xizmat haqisiz)',
                              style: RzType.body.copyWith(color: c.fgMuted),
                            ),
                          ),
                          Text(
                            cart.total.format(),
                            style: RzType.title.copyWith(color: c.fg),
                          ),
                        ],
                      ),
                    ),
                  RzButton(
                    label: 'Tayyor',
                    size: RzButtonSize.lg,
                    fullWidth: true,
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CartRow extends StatelessWidget {
  const _CartRow({
    required this.line,
    required this.onStep,
    required this.onEditMeasured,
    required this.onNote,
    required this.onRemove,
  });

  final DraftLine line;
  final ValueChanged<PosQuantity> onStep;
  final VoidCallback onEditMeasured;
  final VoidCallback onNote;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool measured = line.variant.unit.isMeasured;
    final String mods = line.modifiers
        .map(
          (DraftModifier m) => m.quantity > 1
              ? '${m.modifier.name} x${m.quantity}'
              : m.modifier.name,
        )
        .join(', ');
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  line.name,
                  style: RzType.headline.copyWith(color: c.fg),
                ),
              ),
              Text(line.total.format(), style: RzType.body),
            ],
          ),
          if (mods.isNotEmpty)
            Text('+ $mods', style: RzType.footnote.copyWith(color: c.fgMuted)),
          if (line.note != null)
            Text(
              'Izoh: ${line.note}',
              style: RzType.footnote.copyWith(
                color: c.fgMuted,
                fontStyle: FontStyle.italic,
              ),
            ),
          const SizedBox(height: 6),
          Row(
            children: <Widget>[
              if (measured)
                RzButton(
                  label: line.quantity.label(
                    line.variant.unit,
                    unitLabel: line.variant.unitLabel,
                  ),
                  icon: Icons.edit_outlined,
                  variant: RzButtonVariant.secondary,
                  size: RzButtonSize.lg,
                  onPressed: onEditMeasured,
                )
              else
                PosQuantityStepper(
                  value: line.quantity,
                  onChanged: onStep,
                  onRemove: onRemove,
                  buttonSize: 52,
                ),
              const Spacer(),
              IconButton(
                tooltip: 'Izoh',
                onPressed: onNote,
                icon: Icon(
                  line.note == null
                      ? Icons.edit_note_outlined
                      : Icons.edit_note,
                  color: line.note == null ? c.fgMuted : c.brandSoftFg,
                ),
              ),
              IconButton(
                tooltip: 'Olib tashlash',
                onPressed: onRemove,
                icon: Icon(Icons.delete_outline, color: c.dangerSoftFg),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Qatorga izoh yozish oynasi (controller oyna bilan birga yo'qoladi).
class _NoteDialog extends StatefulWidget {
  const _NoteDialog({required this.line});

  final DraftLine line;

  @override
  State<_NoteDialog> createState() => _NoteDialogState();
}

class _NoteDialogState extends State<_NoteDialog> {
  late final TextEditingController _text = TextEditingController(
    text: widget.line.note,
  );

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(widget.line.name),
    content: TextField(
      controller: _text,
      autofocus: true,
      maxLength: 200,
      textCapitalization: TextCapitalization.sentences,
      decoration: const InputDecoration(hintText: 'Izoh, masalan: piyozsiz'),
    ),
    actions: <Widget>[
      RzButton(
        label: 'Bekor qilish',
        variant: RzButtonVariant.ghost,
        onPressed: () => Navigator.of(context).pop(),
      ),
      RzButton(
        label: 'Saqlash',
        onPressed: () => Navigator.of(context).pop(_text.text),
      ),
    ],
  );
}
