import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'order_draft.dart';
import 'order_format.dart';

/// Kg yoki litr taom miqdori: tez tugmalar (0.5, 0.7, 0.75, 1, 2), kasr bilan terish, variantning qadami va eng kam
/// miqdori; qozonda qancha qolgani ko'rinadi.
Future<PosQuantity?> showQuantityKeypad(
  BuildContext context, {
  required String title,
  required PosVariant variant,
  PosQuantity? initial,
  PosBatch? batch,
  String submitLabel = "Qo'shish",
}) => showDialog<PosQuantity>(
  context: context,
  builder: (BuildContext context) {
    final RzColors c = context.rz;
    // Sarlavha va amallarsiz ixcham oyna: klaviatura planshetning 800px balandligiga sig'adi
    return Dialog(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 380),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 8, 8, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      title,
                      style: RzType.title.copyWith(color: c.fg),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Yopish',
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              Padding(
                padding: const EdgeInsets.only(right: 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Text(
                      variantPriceLabel(variant),
                      style: RzType.body.copyWith(color: c.fgMuted),
                    ),
                    if (batch != null)
                      Text(
                        'Qozonda qoldi: ${batch.quantityLeft.format()}',
                        style: RzType.headline.copyWith(color: c.infoSoftFg),
                      ),
                    const SizedBox(height: 10),
                    PosQuantityKeypad(
                      unit: variant.unit,
                      unitLabel: variant.unitLabel,
                      initial: initial,
                      step: variant.step,
                      min: variant.minimum,
                      submitLabel: submitLabel,
                      keySize: 60,
                      autofocus: true,
                      onSubmit: (PosQuantity q) => Navigator.of(context).pop(q),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  },
);

/// Bir nechta variantli taom: qaysi biri (porsiya, 0.7, lagan ...).
Future<PosVariant?> showVariantPicker(
  BuildContext context, {
  required PosProduct product,
  required PosCatalog catalog,
}) => showDialog<PosVariant>(
  context: context,
  builder: (BuildContext context) => AlertDialog(
    title: Text(product.name),
    content: SizedBox(
      width: 520,
      child: SingleChildScrollView(
        child: Wrap(
          spacing: 10,
          runSpacing: 10,
          children: <Widget>[
            for (final PosVariant v in product.variants)
              SizedBox(
                width: 160,
                child: PosTileButton(
                  label: v.name.isEmpty ? product.name : v.name,
                  subtitle: variantPriceLabel(v),
                  badge: catalog.batchFor(v) == null
                      ? null
                      : 'Qoldi: ${catalog.batchFor(v)!.quantityLeft.format()}',
                  unavailable: !catalog.isVariantAvailable(v),
                  unavailableLabel:
                      catalog.unavailableReasonOf(v)?.label ?? 'Tugadi',
                  onTap: () => Navigator.of(context).pop(v),
                ),
              ),
          ],
        ),
      ),
    ),
    actions: <Widget>[
      RzButton(
        label: 'Bekor qilish',
        variant: RzButtonVariant.ghost,
        onPressed: () => Navigator.of(context).pop(),
      ),
    ],
  ),
);

/// Qatorni tahrirlash natijasi ([remove] — qatorni olib tashlash).
class LineEdit {
  const LineEdit({
    required this.quantity,
    required this.modifiers,
    this.note,
    this.seatNo,
    this.fireMode,
    this.remove = false,
  });

  const LineEdit.remove()
    : quantity = PosQuantity.zero,
      modifiers = const <String, int>{},
      note = null,
      seatNo = null,
      fireMode = null,
      remove = true;

  final PosQuantity quantity;
  final Map<String, int> modifiers;
  final String? note;
  final int? seatNo;
  final PosFireMode? fireMode;
  final bool remove;
}

/// Tez izohlar.
const List<String> quickNotes = <String>[
  'piyozsiz',
  'achchiq',
  "kamroq yog'",
  'tuzsiz',
  'tezroq',
];

/// Yangi bosqich qatori: miqdor, qo'shimchalar, izoh, mehmon (o'rin) raqami, "hozir / ushlab turish".
Future<LineEdit?> showLineEditor(
  BuildContext context, {
  required String title,
  required PosProduct product,
  required PosVariant variant,
  required PosQuantity quantity,
  Map<String, int> modifiers = const <String, int>{},
  String? note,
  int? seatNo,
  PosFireMode? fireMode,
  PosAvailability? availability,
  bool isNew = false,
}) => showDialog<LineEdit>(
  context: context,
  builder: (BuildContext context) => _LineEditor(
    title: title,
    product: product,
    variant: variant,
    quantity: quantity,
    modifiers: modifiers,
    note: note,
    seatNo: seatNo,
    fireMode: fireMode,
    availability: availability,
    isNew: isNew,
  ),
);

class _LineEditor extends StatefulWidget {
  const _LineEditor({
    required this.title,
    required this.product,
    required this.variant,
    required this.quantity,
    required this.modifiers,
    required this.note,
    required this.seatNo,
    required this.fireMode,
    required this.availability,
    required this.isNew,
  });

  final String title;
  final PosProduct product;
  final PosVariant variant;
  final PosQuantity quantity;
  final Map<String, int> modifiers;
  final String? note;
  final int? seatNo;
  final PosFireMode? fireMode;
  final PosAvailability? availability;
  final bool isNew;

  @override
  State<_LineEditor> createState() => _LineEditorState();
}

class _LineEditorState extends State<_LineEditor> {
  late PosQuantity _qty = widget.quantity;
  late Map<String, int> _mods = Map<String, int>.of(widget.modifiers);
  late final TextEditingController _note = TextEditingController(
    text: widget.note ?? '',
  );
  late int? _seat = widget.seatNo;
  late PosFireMode? _fire = widget.fireMode;
  bool _tried = false;

  late final List<PosModifierGroupSpec> _groups = modifierSpecs(
    widget.product,
    availability: widget.availability,
  );

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _keypad() async {
    final PosQuantity? q = await showQuantityKeypad(
      context,
      title: widget.title,
      variant: widget.variant,
      initial: _qty,
      submitLabel: 'Tayyor',
    );
    if (q != null && mounted) setState(() => _qty = q);
  }

  void _save() {
    if (validateModifierSelection(_groups, _mods).isNotEmpty) {
      setState(() => _tried = true);
      return;
    }
    Navigator.of(context).pop(
      LineEdit(
        quantity: _qty,
        modifiers: _mods,
        note: _note.text.trim().isEmpty ? null : _note.text.trim(),
        seatNo: _seat,
        fireMode: _fire,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosVariant v = widget.variant;
    Widget label(String text) => Padding(
      padding: const EdgeInsets.only(top: 16, bottom: 8),
      child: Text(text, style: RzType.headline.copyWith(color: c.fg)),
    );
    return AlertDialog(
      title: Text(widget.title),
      content: SizedBox(
        width: 560,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                variantPriceLabel(v),
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
              label('Miqdor'),
              Row(
                children: <Widget>[
                  PosQuantityStepper(
                    value: _qty,
                    unit: v.unit,
                    unitLabel: v.unitLabel,
                    step: v.step,
                    min: v.minimum,
                    onChanged: (PosQuantity q) => setState(() => _qty = q),
                  ),
                  if (v.unit.isMeasured) ...<Widget>[
                    const SizedBox(width: 12),
                    RzButton(
                      label: 'Klaviatura',
                      icon: Icons.dialpad,
                      variant: RzButtonVariant.secondary,
                      onPressed: _keypad,
                    ),
                  ],
                ],
              ),
              if (_groups.isNotEmpty) ...<Widget>[
                label("Qo'shimchalar"),
                PosModifierPicker(
                  groups: _groups,
                  selection: _mods,
                  showErrors: _tried,
                  onChanged: (Map<String, int> m) => setState(() => _mods = m),
                ),
              ],
              label('Izoh'),
              TextField(
                controller: _note,
                maxLength: 120,
                decoration: const InputDecoration(
                  hintText: 'Masalan: piyozsiz',
                  border: OutlineInputBorder(),
                ),
              ),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  for (final String n in quickNotes)
                    ActionChip(
                      label: Text(n),
                      materialTapTargetSize: MaterialTapTargetSize.padded,
                      onPressed: () => setState(() {
                        final String t = _note.text.trim();
                        _note.text = t.isEmpty ? n : '$t, $n';
                      }),
                    ),
                ],
              ),
              label("Mehmon (o'rin)"),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  ChoiceChip(
                    label: const Text('Umumiy'),
                    selected: _seat == null,
                    materialTapTargetSize: MaterialTapTargetSize.padded,
                    onSelected: (_) => setState(() => _seat = null),
                  ),
                  for (var i = 1; i <= 10; i++)
                    ChoiceChip(
                      label: Text('$i'),
                      selected: _seat == i,
                      materialTapTargetSize: MaterialTapTargetSize.padded,
                      onSelected: (_) => setState(() => _seat = i),
                    ),
                ],
              ),
              label('Oshxonaga'),
              SegmentedButton<PosFireMode?>(
                segments: const <ButtonSegment<PosFireMode?>>[
                  ButtonSegment<PosFireMode?>(
                    value: null,
                    label: Text('Bosqichdagidek'),
                  ),
                  ButtonSegment<PosFireMode?>(
                    value: PosFireMode.now,
                    label: Text('Hozir'),
                    icon: Icon(Icons.send_outlined),
                  ),
                  ButtonSegment<PosFireMode?>(
                    value: PosFireMode.hold,
                    label: Text('Ushlab turish'),
                    icon: Icon(Icons.pause_circle_outline),
                  ),
                ],
                selected: <PosFireMode?>{_fire},
                onSelectionChanged: (Set<PosFireMode?> s) =>
                    setState(() => _fire = s.first),
              ),
            ],
          ),
        ),
      ),
      actions: <Widget>[
        if (!widget.isNew)
          RzButton(
            label: 'Olib tashlash',
            icon: Icons.delete_outline,
            variant: RzButtonVariant.danger,
            onPressed: () => Navigator.of(context).pop(const LineEdit.remove()),
          ),
        RzButton(
          label: 'Bekor qilish',
          variant: RzButtonVariant.ghost,
          onPressed: () => Navigator.of(context).pop(),
        ),
        RzButton(
          label: widget.isNew ? "Qo'shish" : 'Saqlash',
          size: RzButtonSize.lg,
          onPressed: _save,
        ),
      ],
    );
  }
}

/// Bosqichdagi (serverdagi) qator amallari.
enum LineAction { sendNow, served, voidLine }

Future<LineAction?> showLineActions(
  BuildContext context, {
  required PosOrderItem item,
  required bool open,
}) {
  final List<(LineAction, String, IconData, RzButtonVariant)> actions =
      <(LineAction, String, IconData, RzButtonVariant)>[
        if (open && item.kitchenStatus == PosKitchenStatus.pending)
          (
            LineAction.sendNow,
            'Hozir oshxonaga yuborish',
            Icons.send_outlined,
            RzButtonVariant.primary,
          ),
        if (open &&
            (item.kitchenStatus == PosKitchenStatus.sent ||
                item.kitchenStatus == PosKitchenStatus.ready))
          (
            LineAction.served,
            'Berildi',
            Icons.room_service_outlined,
            RzButtonVariant.success,
          ),
        if (open && !item.isVoid)
          (
            LineAction.voidLine,
            "O'chirish",
            Icons.delete_outline,
            RzButtonVariant.danger,
          ),
      ];
  return showModalBottomSheet<LineAction>(
    context: context,
    showDragHandle: true,
    builder: (BuildContext context) {
      final RzColors c = context.rz;
      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                '${itemQuantityLabel(item)} ${item.displayName}',
                style: RzType.title.copyWith(color: c.fg),
              ),
              const SizedBox(height: 4),
              Row(
                children: <Widget>[
                  kitchenStatusChip(item),
                  const SizedBox(width: 8),
                  Text(
                    item.totalPrice.format(),
                    style: RzType.body.copyWith(color: c.fgMuted),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (actions.isEmpty)
                Text(
                  "Bu qator bilan amal yo'q.",
                  style: RzType.body.copyWith(color: c.fgMuted),
                ),
              for (final (LineAction a, String l, IconData i, RzButtonVariant v)
                  in actions)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: RzButton(
                    label: l,
                    icon: i,
                    variant: v,
                    size: RzButtonSize.lg,
                    fullWidth: true,
                    onPressed: () => Navigator.of(context).pop(a),
                  ),
                ),
            ],
          ),
        ),
      );
    },
  );
}
