import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';
import 'banquet_format.dart';

/// P6-51/P6-52: zalni vaqtincha band qilish (yangi tadbir) yoki tadbirni o'zgartirish (`banquets.manage`): sana, vaqt,
/// zallar (bir nechtasi — birlashtiriladi), tur, mijoz, kafolatlangan mehmonlar, paket, qo'shimchalar. O'zgartirishda
/// sana, vaqt va zal o'zgarmaydi (ko'chirish — panelda); to'lovlar saqlanadi, to'lanmagan qatorlar qayta rejalanadi.
/// Natija — saqlangan tadbir.
class BanquetFormPage extends StatefulWidget {
  const BanquetFormPage({
    super.key,
    this.event,
    this.day,
    this.halls = const <PosBanquetCalendarHall>[],
    this.hallId,
  });

  /// O'zgartiriladigan tadbir (`null` — yangi).
  final PosBanquetEvent? event;

  /// Yangi tadbir kuni.
  final DateTime? day;
  final List<PosBanquetCalendarHall> halls;
  final int? hallId;

  @override
  State<BanquetFormPage> createState() => _BanquetFormPageState();
}

class _BanquetFormPageState extends State<BanquetFormPage> {
  static const List<(String, String)> _types = <(String, String)>[
    ('toy', "To'y"),
    ('osh', 'Osh'),
    ('birthday', "Tug'ilgan kun"),
    ('corporate', 'Korporativ'),
    ('other', 'Boshqa'),
  ];

  PosBanquetCatalog? _catalog;
  String? _error;
  bool _busy = false;
  bool _started = false;

  late DateTime _day;
  PosBanquetSlot _slot = PosBanquetSlot.evening;
  final Set<int> _halls = <int>{};
  String _type = 'toy';
  int? _package;
  final TextEditingController _title = TextEditingController();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _guests = TextEditingController();
  final TextEditingController _note = TextEditingController();

  /// Katalog qo'shimchasi id → miqdor.
  final Map<int, int> _extras = <int, int>{};

  bool get _editing => widget.event != null;

  PosScope get _scope => PosScope.of(context);

  @override
  void initState() {
    super.initState();
    final PosBanquetEvent? e = widget.event;
    if (e != null) {
      _day = banquetDay(e.eventDate);
      _slot = e.slot;
      _halls.addAll(e.hallIds);
      _type = e.eventType;
      _package = e.packageId;
      _title.text = e.title ?? '';
      _name.text = e.customerName;
      _phone.text = (e.customerPhone ?? '').replaceFirst('+998', '');
      _guests.text = '${e.guestsGuaranteed}';
      _note.text = e.note ?? '';
      for (final PosBanquetLine l in e.lines) {
        if (l.kind == 'extra' && l.extraId != null) {
          _extras[l.extraId!] = l.quantity.milli ~/ 1000;
        }
      }
    } else {
      final DateTime d = widget.day ?? DateTime.now();
      _day = DateTime(d.year, d.month, d.day);
      if (widget.hallId != null) _halls.add(widget.hallId!);
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      unawaited(_load());
    }
  }

  @override
  void dispose() {
    _title.dispose();
    _name.dispose();
    _phone.dispose();
    _guests.dispose();
    _note.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final PosBanquetCatalog c = await _scope.data.banquets.catalog();
      if (!mounted) return;
      setState(() {
        _catalog = c;
        _package ??= c.packages.isEmpty ? null : c.packages.first.id;
      });
    } on PosException catch (e) {
      if (mounted) {
        setState(
          () => _error = e is PosOfflineUnavailable
              ? banquetsOfflineText
              : e.message,
        );
      }
    }
  }

  String? _phoneValue() {
    final String digits = _phone.text.replaceAll(RegExp(r'\D'), '');
    if (digits.isEmpty) return null;
    if (digits.length == 9) return '+998$digits';
    if (digits.length == 12 && digits.startsWith('998')) return '+$digits';
    return '';
  }

  List<PosBanquetExtraInput> _extrasInput() {
    final PosBanquetEvent? e = widget.event;
    final Map<int, String> lineOf = <int, String>{
      for (final PosBanquetLine l in e?.lines ?? const <PosBanquetLine>[])
        if (l.kind == 'extra' && l.extraId != null) l.extraId!: l.uuid,
    };
    return <PosBanquetExtraInput>[
      for (final MapEntry<int, int> x in _extras.entries)
        if (x.value > 0)
          PosBanquetExtraInput.catalog(
            x.key,
            quantity: PosQuantity.whole(x.value),
            lineUuid: lineOf[x.key],
          ),
      // Free-text extras written in the panel stay as they are
      for (final PosBanquetLine l in e?.lines ?? const <PosBanquetLine>[])
        if (l.kind == 'extra' && l.extraId == null)
          PosBanquetExtraInput.free(
            name: l.name,
            unitPrice: l.unitPrice,
            perPerson: l.perPerson,
            quantity: l.quantity,
            note: l.note,
            lineUuid: l.uuid,
          ),
    ];
  }

  Future<void> _save() async {
    final int? guests = int.tryParse(_guests.text.trim());
    final String? phone = _phoneValue();
    final String? error = switch (null) {
      _ when _name.text.trim().isEmpty => 'Mijoz ismini yozing.',
      _ when phone == null || phone.isEmpty =>
        "Telefon raqamini to'liq kiriting (90 123 45 67).",
      _ when guests == null || guests < 1 => 'Mehmonlar sonini kiriting.',
      _ when !_editing && _halls.isEmpty => 'Zalni tanlang.',
      _ => null,
    };
    if (error != null) {
      setState(() => _error = error);
      return;
    }
    final PosBanquetDraft draft = _editing
        ? PosBanquetDraft(
            title: _title.text.trim(),
            customerName: _name.text.trim(),
            customerPhone: phone,
            guestsGuaranteed: guests,
            extras: _extrasInput(),
            note: _note.text.trim().isEmpty ? null : _note.text.trim(),
          )
        : PosBanquetDraft(
            eventDate: isoDay(_day),
            slot: _slot,
            hallIds: _halls.toList()..sort(),
            eventType: _type,
            title: _title.text.trim().isEmpty ? null : _title.text.trim(),
            customerName: _name.text.trim(),
            customerPhone: phone,
            guestsGuaranteed: guests,
            packageId: _package,
            extras: _extrasInput(),
            note: _note.text.trim().isEmpty ? null : _note.text.trim(),
          );
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final PosBanquetEvent saved = _editing
          ? await _scope.data.banquets.update(
              widget.event!.uuid,
              draft,
              version: widget.event!.version,
            )
          : await _scope.data.banquets.createHold(draft);
      if (mounted) Navigator.pop(context, saved);
    } on PosException catch (e) {
      if (mounted) {
        setState(
          () => _error = switch (e) {
            final PosOfflineUnavailable _ => banquetsOfflineText,
            final PosValidationException v => v.firstError,
            _ => e.message,
          },
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pickDay() async {
    final DateTime now = _scope.services.now().toLocal();
    final DateTime? d = await showDatePicker(
      context: context,
      initialDate: _day.isBefore(now) ? now : _day,
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: DateTime(now.year + 2, 12, 31),
    );
    if (d != null) setState(() => _day = d);
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosBanquetCatalog? cat = _catalog;
    return Scaffold(
      backgroundColor: c.page,
      appBar: AppBar(
        title: Text(_editing ? "Tadbirni o'zgartirish" : 'Yangi tadbir'),
      ),
      body: cat == null && _error == null
          ? const LoadingBox()
          : Align(
              alignment: Alignment.topCenter,
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 760),
                child: SingleChildScrollView(
                  key: const ValueKey<String>('banquet-form'),
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      if (!_editing) ...<Widget>[
                        PosCard(
                          title: 'Sana, vaqt va zal',
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: <Widget>[
                              OutlinedButton.icon(
                                key: const ValueKey<String>('banquet-form-day'),
                                icon: const Icon(Icons.calendar_month_outlined),
                                label: Text(longDay(_day)),
                                onPressed: _pickDay,
                              ),
                              const SizedBox(height: 12),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: <Widget>[
                                  for (final PosBanquetSlot s
                                      in <PosBanquetSlot>[
                                        PosBanquetSlot.morning,
                                        PosBanquetSlot.day,
                                        PosBanquetSlot.evening,
                                      ])
                                    ChoiceChip(
                                      key: ValueKey<String>(
                                        'banquet-slot-${s.wire}',
                                      ),
                                      label: Text(
                                        cat?.slots[s] == null
                                            ? s.label
                                            : '${s.label} ${cat!.slots[s]!.$1}–${cat.slots[s]!.$2}',
                                      ),
                                      selected: _slot == s,
                                      onSelected: (_) =>
                                          setState(() => _slot = s),
                                    ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: <Widget>[
                                  for (final PosBanquetCalendarHall h
                                      in widget.halls)
                                    FilterChip(
                                      key: ValueKey<String>(
                                        'banquet-hall-${h.id}',
                                      ),
                                      label: Text(
                                        cat?.hallRates[h.id]?[_slot] == null
                                            ? h.name
                                            : '${h.name} · ${cat!.hallRates[h.id]![_slot]!.format()}',
                                      ),
                                      selected: _halls.contains(h.id),
                                      onSelected: (bool v) => setState(
                                        () => v
                                            ? _halls.add(h.id)
                                            : _halls.remove(h.id),
                                      ),
                                    ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: <Widget>[
                                  for (final (String wire, String label)
                                      in _types)
                                    ChoiceChip(
                                      label: Text(label),
                                      selected: _type == wire,
                                      onSelected: (_) =>
                                          setState(() => _type = wire),
                                    ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 12),
                      ],
                      PosCard(
                        title: 'Mijoz va mehmonlar',
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: <Widget>[
                            TextField(
                              key: const ValueKey<String>('banquet-form-title'),
                              controller: _title,
                              decoration: const InputDecoration(
                                labelText:
                                    "Nomi (ixtiyoriy): Aziz va Malika to'yi",
                              ),
                            ),
                            const SizedBox(height: 8),
                            TextField(
                              key: const ValueKey<String>('banquet-form-name'),
                              controller: _name,
                              decoration: const InputDecoration(
                                labelText: 'Mijoz',
                              ),
                            ),
                            const SizedBox(height: 8),
                            TextField(
                              key: const ValueKey<String>('banquet-form-phone'),
                              controller: _phone,
                              keyboardType: TextInputType.phone,
                              decoration: const InputDecoration(
                                labelText: 'Telefon',
                                prefixText: '+998 ',
                              ),
                            ),
                            const SizedBox(height: 8),
                            TextField(
                              key: const ValueKey<String>(
                                'banquet-form-guests',
                              ),
                              controller: _guests,
                              keyboardType: TextInputType.number,
                              inputFormatters: <TextInputFormatter>[
                                FilteringTextInputFormatter.digitsOnly,
                              ],
                              decoration: const InputDecoration(
                                labelText: 'Kafolatlangan mehmonlar',
                                suffixText: 'kishi',
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (cat != null) ...<Widget>[
                        const SizedBox(height: 12),
                        if (!_editing && cat.packages.isNotEmpty) ...<Widget>[
                          PosCard(
                            title: 'Paket',
                            child: Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: <Widget>[
                                for (final PosBanquetPackage p in cat.packages)
                                  ChoiceChip(
                                    key: ValueKey<String>(
                                      'banquet-package-${p.id}',
                                    ),
                                    label: Text(
                                      '${p.name} · ${p.perPersonPrice.format()} / kishi'
                                      '${p.minimumGuests > 0 ? ' (kamida ${p.minimumGuests})' : ''}',
                                    ),
                                    selected: _package == p.id,
                                    onSelected: (_) =>
                                        setState(() => _package = p.id),
                                  ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 12),
                        ],
                        if (cat.extras.isNotEmpty)
                          PosCard(
                            title: "Qo'shimchalar",
                            child: Column(
                              children: <Widget>[
                                for (final PosBanquetExtra x in cat.extras)
                                  _extraRow(c, x),
                              ],
                            ),
                          ),
                      ],
                      const SizedBox(height: 12),
                      TextField(
                        controller: _note,
                        maxLines: 2,
                        minLines: 1,
                        decoration: const InputDecoration(labelText: 'Izoh'),
                      ),
                      if (_error != null) ...<Widget>[
                        const SizedBox(height: 12),
                        NoticeBanner(
                          key: const ValueKey<String>('banquet-form-error'),
                          message: _error!,
                        ),
                      ],
                      const SizedBox(height: 16),
                      RzButton(
                        key: const ValueKey<String>('banquet-form-save'),
                        label: _editing ? 'Saqlash' : 'Zalni band qilish',
                        icon: Icons.event_available_outlined,
                        size: RzButtonSize.lg,
                        loading: _busy,
                        onPressed: _busy || cat == null ? null : _save,
                      ),
                      if (!_editing) ...<Widget>[
                        const SizedBox(height: 8),
                        Text(
                          cat == null || cat.holdHours == 0
                              ? "Zal zaklad olinguncha vaqtincha band turadi."
                              : "Zal ${cat.holdHours} soat vaqtincha band turadi: shu vaqtda zaklad olinmasa, o'zi bo'shaydi.",
                          style: RzType.footnote.copyWith(color: c.fgMuted),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ),
    );
  }

  Widget _extraRow(RzColors c, PosBanquetExtra x) {
    final int qty = _extras[x.id] ?? 0;
    return Row(
      children: <Widget>[
        Expanded(
          child: Text(
            '${x.name} · ${x.price.format()}${x.perPerson ? ' / kishi' : ''}',
            style: RzType.body.copyWith(color: c.fg),
          ),
        ),
        IconButton(
          tooltip: 'Kamaytirish',
          icon: const Icon(Icons.remove_circle_outline_rounded),
          onPressed: qty == 0
              ? null
              : () => setState(() => _extras[x.id] = qty - 1),
        ),
        SizedBox(
          width: 32,
          child: Text(
            '$qty',
            key: ValueKey<String>('banquet-extra-qty-${x.id}'),
            textAlign: TextAlign.center,
            style: RzType.headline.copyWith(color: c.fg),
          ),
        ),
        IconButton(
          key: ValueKey<String>('banquet-extra-add-${x.id}'),
          tooltip: "Qo'shish",
          icon: const Icon(Icons.add_circle_outline_rounded),
          onPressed: () => setState(() => _extras[x.id] = qty + 1),
        ),
      ],
    );
  }
}
