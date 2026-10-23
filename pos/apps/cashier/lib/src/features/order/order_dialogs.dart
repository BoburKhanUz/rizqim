import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'order_format.dart';

/// Sabab oynasi natijasi.
class ReasonResult {
  const ReasonResult(this.reason, {this.restock});

  final String reason;

  /// O'chirishda: qoldiqni (qozon ulushini) qaytarish — taom pishmagan bo'lsa.
  final bool? restock;
}

/// Sabab so'raydi (o'chirish, xizmat qatorini olib tashlash, bekor qilish): tez sabablar va matn. [required] bo'lmasa
/// bo'sh sabab ham bo'ladi.
Future<ReasonResult?> showReasonDialog(
  BuildContext context, {
  required String title,
  String? message,
  required List<String> presets,
  required String confirmLabel,
  bool required = true,
  bool askRestock = false,
  bool danger = true,
}) => showDialog<ReasonResult>(
  context: context,
  builder: (BuildContext context) => _ReasonDialog(
    title: title,
    message: message,
    presets: presets,
    confirmLabel: confirmLabel,
    required: required,
    askRestock: askRestock,
    danger: danger,
  ),
);

class _ReasonDialog extends StatefulWidget {
  const _ReasonDialog({
    required this.title,
    required this.message,
    required this.presets,
    required this.confirmLabel,
    required this.required,
    required this.askRestock,
    required this.danger,
  });

  final String title;
  final String? message;
  final List<String> presets;
  final String confirmLabel;
  final bool required;
  final bool askRestock;
  final bool danger;

  @override
  State<_ReasonDialog> createState() => _ReasonDialogState();
}

class _ReasonDialogState extends State<_ReasonDialog> {
  final TextEditingController _text = TextEditingController();
  bool _restock = false;

  @override
  void initState() {
    super.initState();
    _text.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final String reason = _text.text.trim();
    final bool valid = !widget.required || reason.isNotEmpty;
    return AlertDialog(
      title: Text(widget.title),
      content: SizedBox(
        width: 460,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              if (widget.message != null) ...<Widget>[
                Text(
                  widget.message!,
                  style: RzType.body.copyWith(color: c.fgMuted),
                ),
                const SizedBox(height: 12),
              ],
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  for (final String p in widget.presets)
                    ChoiceChip(
                      label: Text(p),
                      selected: reason == p,
                      materialTapTargetSize: MaterialTapTargetSize.padded,
                      onSelected: (_) => _text.text = p,
                    ),
                ],
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _text,
                maxLength: 200,
                decoration: InputDecoration(
                  labelText: widget.required ? 'Sabab' : 'Sabab (ixtiyoriy)',
                  border: const OutlineInputBorder(),
                ),
              ),
              if (widget.askRestock)
                CheckboxListTile(
                  value: _restock,
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  title: const Text('Taom pishmagan: qoldiqqa qaytarish'),
                  onChanged: (bool? v) => setState(() => _restock = v ?? false),
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
        RzButton(
          label: widget.confirmLabel,
          size: RzButtonSize.lg,
          variant: widget.danger
              ? RzButtonVariant.danger
              : RzButtonVariant.primary,
          onPressed: valid
              ? () => Navigator.of(context).pop(
                  ReasonResult(
                    reason,
                    restock: widget.askRestock ? _restock : null,
                  ),
                )
              : null,
        ),
      ],
    );
  }
}

/// Mehmonlar soni: katta stepper va tez tugmalar.
Future<int?> showGuestsDialog(
  BuildContext context, {
  required int initial,
  String title = 'Mehmonlar soni',
}) => showDialog<int>(
  context: context,
  builder: (BuildContext context) {
    var value = initial < 1 ? 1 : initial;
    return StatefulBuilder(
      builder: (BuildContext context, StateSetter setState) => AlertDialog(
        title: Text(title),
        content: SizedBox(
          width: 380,
          child: GuestsPicker(
            value: value,
            onChanged: (int v) => setState(() => value = v),
          ),
        ),
        actions: <Widget>[
          RzButton(
            label: 'Bekor qilish',
            variant: RzButtonVariant.ghost,
            onPressed: () => Navigator.of(context).pop(),
          ),
          RzButton(
            label: 'Saqlash',
            size: RzButtonSize.lg,
            onPressed: () => Navigator.of(context).pop(value),
          ),
        ],
      ),
    );
  },
);

/// Mehmonlar soni tanlagichi (buyurtma ochish va mehmonlar oynasida).
class GuestsPicker extends StatelessWidget {
  const GuestsPicker({super.key, required this.value, required this.onChanged});

  final int value;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    children: <Widget>[
      PosQuantityStepper(
        value: PosQuantity.whole(value),
        max: const PosQuantity.whole(99),
        onChanged: (PosQuantity q) => onChanged(q.wholePart),
      ),
      const SizedBox(height: 12),
      Wrap(
        spacing: 8,
        runSpacing: 8,
        alignment: WrapAlignment.center,
        children: <Widget>[
          for (final int n in const <int>[2, 4, 6, 8, 10, 12, 15, 20])
            ChoiceChip(
              label: Text('$n'),
              selected: value == n,
              materialTapTargetSize: MaterialTapTargetSize.padded,
              onSelected: (_) => onChanged(n),
            ),
        ],
      ),
    ],
  );
}

/// Buyurtmani boshqa joyga ko'chirish: zal xaritasi qayta so'raladi; band xona tanlanmaydi (unga — birlashtirish).
Future<FloorTable?> showMoveDialog(
  BuildContext context, {
  required Future<PosFloor> Function() loadFloor,
  int? currentTableId,
}) => showDialog<FloorTable>(
  context: context,
  builder: (BuildContext context) => AlertDialog(
    title: const Text("Boshqa joyga ko'chirish"),
    content: SizedBox(
      width: 720,
      height: 440,
      child: _FloorPicker(
        loadFloor: loadFloor,
        builder: (BuildContext context, PosFloor floor) => ListView(
          children: <Widget>[
            for (final FloorHall h in floor.halls)
              if (h.tables.any((FloorTable t) => t.id != currentTableId)) ...[
                Padding(
                  padding: const EdgeInsets.fromLTRB(4, 12, 4, 8),
                  child: Text(
                    h.name,
                    style: RzType.headline.copyWith(color: context.rz.fg),
                  ),
                ),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    for (final FloorTable t in h.tables)
                      if (t.id != currentTableId)
                        SizedBox(
                          width: 176,
                          height: 96,
                          child: Opacity(
                            opacity: _canMoveTo(t) ? 1 : 0.4,
                            child: FloorTile(
                              item: FloorMapItem(
                                id: '${t.id}',
                                label: t.name,
                                kind: t.kind,
                                status: t.status,
                                rect: Rect.zero,
                                seats: t.seats,
                                guests: t.orders.isEmpty
                                    ? null
                                    : t.orders.fold<int>(
                                        0,
                                        (int s, FloorOrder o) =>
                                            s + (o.guestsCount ?? 0),
                                      ),
                              ),
                              now: DateTime.now(),
                              onTap: _canMoveTo(t)
                                  ? () => Navigator.of(context).pop(t)
                                  : null,
                            ),
                          ),
                        ),
                  ],
                ),
              ],
            const SizedBox(height: 12),
            Text(
              "Band xonaga ko'chirib bo'lmaydi: u holda buyurtmalarni birlashtiring.",
              style: RzType.footnote.copyWith(color: context.rz.fgMuted),
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

bool _canMoveTo(FloorTable t) =>
    t.status != FloorTableStatus.inactive &&
    !(t.kind == FloorTableKind.room && t.orders.isNotEmpty);

/// Boshqa ochiq zal buyurtmasini shu buyurtmaga qo'shish (manba bekor bo'ladi).
Future<FloorOrder?> showMergeDialog(
  BuildContext context, {
  required Future<PosFloor> Function() loadFloor,
  required String currentUuid,
  required DateTime now,
}) => showDialog<FloorOrder>(
  context: context,
  builder: (BuildContext context) => AlertDialog(
    title: const Text('Buyurtmalarni birlashtirish'),
    content: SizedBox(
      width: 560,
      height: 420,
      child: _FloorPicker(
        loadFloor: loadFloor,
        builder: (BuildContext context, PosFloor floor) {
          final List<(String, FloorOrder)> rows = <(String, FloorOrder)>[
            for (final FloorTable t in floor.tables)
              for (final FloorOrder o in t.orders)
                if (o.uuid != currentUuid && !o.billPrinted) (t.name, o),
            for (final FloorOrder o in floor.untabledOrders)
              if (o.uuid != currentUuid &&
                  PosChannel.fromWire(o.channel) == PosChannel.hall)
                ('Zalda, stolsiz', o),
          ];
          final RzColors c = context.rz;
          if (rows.isEmpty) {
            return const EmptyState(
              icon: Icons.call_merge,
              title: "Boshqa ochiq buyurtma yo'q",
              message: "Birlashtirish uchun zalda yana bir ochiq buyurtma bo'lishi kerak.",
            );
          }
          return ListView(
            children: <Widget>[
              Text(
                "Tanlangan buyurtma taomlari va mehmonlari shu buyurtmaga o'tadi, u esa yopiladi.",
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 8),
              for (final (String place, FloorOrder o) in rows)
                Card(
                  child: ListTile(
                    minTileHeight: 64,
                    leading: const Icon(Icons.receipt_long_outlined),
                    title: Text('$place · ${o.shortCode ?? ''}'),
                    subtitle: Text(
                      '${o.guestsCount ?? 0} mehmon · ${o.itemsCount} taom · '
                      '${o.openedAt == null ? '' : formatOpenFor(now.difference(o.openedAt!))}',
                    ),
                    trailing: Text(
                      o.totalPrice.format(),
                      style: RzType.headline.copyWith(color: c.fg),
                    ),
                    onTap: () => Navigator.of(context).pop(o),
                  ),
                ),
            ],
          );
        },
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

class _FloorPicker extends StatefulWidget {
  const _FloorPicker({required this.loadFloor, required this.builder});

  final Future<PosFloor> Function() loadFloor;
  final Widget Function(BuildContext context, PosFloor floor) builder;

  @override
  State<_FloorPicker> createState() => _FloorPickerState();
}

class _FloorPickerState extends State<_FloorPicker> {
  late Future<PosFloor> _future = widget.loadFloor();

  @override
  Widget build(BuildContext context) => FutureBuilder<PosFloor>(
    future: _future,
    builder: (BuildContext context, AsyncSnapshot<PosFloor> s) {
      if (s.hasError) {
        return InlineError(
          message: posErrorText(s.error!),
          onRetry: () => setState(() => _future = widget.loadFloor()),
        );
      }
      if (!s.hasData) return const Center(child: CircularProgressIndicator());
      return widget.builder(context, s.data!);
    },
  );
}

/// Ha / yo'q.
Future<bool> confirmDialog(
  BuildContext context, {
  required String title,
  required String message,
  required String confirmLabel,
  String cancelLabel = 'Qolish',
  bool danger = false,
}) async =>
    await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: <Widget>[
          RzButton(
            label: cancelLabel,
            variant: RzButtonVariant.ghost,
            onPressed: () => Navigator.of(context).pop(false),
          ),
          RzButton(
            label: confirmLabel,
            variant: danger ? RzButtonVariant.danger : RzButtonVariant.primary,
            onPressed: () => Navigator.of(context).pop(true),
          ),
        ],
      ),
    ) ??
    false;
