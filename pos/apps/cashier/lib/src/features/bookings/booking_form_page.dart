import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';
import 'preorder_picker.dart';

/// Tadbir turlari (`event_type`).
const Map<String, String> eventTypes = <String, String>{
  'gap': 'Gap',
  'ziyofat': 'Ziyofat',
  'tugilgan_kun': "Tug'ilgan kun",
  'nahor_oshi': 'Nahor oshi',
  'korporativ': 'Korporativ',
  'other': 'Boshqa',
};

/// Bron manbalari (`source`).
const Map<String, String> bookingSources = <String, String>{
  'phone': 'Telefon',
  'telegram': 'Telegram',
  'instagram': 'Instagram',
  'walk_in': "O'zi keldi",
  'other': 'Boshqa',
};

/// Bron yaratish yoki o'zgartirish (POS_SPEC 6.1): xona (banketda bir nechta), sana va vaqt (davomiylik — filial
/// sloti, standart 5 soat), mehmonlar, tadbir turi, aloqa (ism, telefon), manba, minimal summa, izoh; yangi bronda
/// oldindan buyurtma ham (taom, kg, tayyor bo'lish vaqti). Xona band bo'lsa server to'qnashuvni aytadi.
class BookingFormPage extends StatefulWidget {
  const BookingFormPage({
    super.key,
    required this.rooms,
    this.booking,
    this.roomId,
    this.start,
  });

  final List<PosCalendarRoom> rooms;
  final PosBooking? booking;
  final int? roomId;
  final DateTime? start;

  @override
  State<BookingFormPage> createState() => _BookingFormPageState();
}

class _BookingFormPageState extends State<BookingFormPage> {
  late final PosBooking? b = widget.booking;
  late int? _room = b?.diningTableId ?? widget.roomId;
  late final Set<int> _extra = <int>{
    ...?b?.tableIds.where((int id) => id != b?.diningTableId),
  };
  late DateTime _start = (b?.startsAt ?? widget.start ?? _defaultStart())
      .toLocal();
  late int _minutes = b == null
      ? 300
      : b!.endsAt.difference(b!.startsAt).inMinutes;
  late int _guests = b?.guestsPlanned ?? 8;
  late String? _event = b?.eventType ?? 'gap';
  late String _source = b?.source ?? 'phone';
  late final TextEditingController _name = TextEditingController(
    text: b?.contactName ?? '',
  );
  late final TextEditingController _phone = TextEditingController(
    text: b?.contactPhone ?? '',
  );
  late final TextEditingController _minSpend = TextEditingController(
    text: moneyText(b?.minSpend),
  );
  late final TextEditingController _note = TextEditingController(
    text: b?.note ?? '',
  );
  bool _confirm = true;
  final List<PreorderDraft> _items = <PreorderDraft>[];
  bool _busy = false;
  String? _error;
  bool _slotApplied = false;

  static DateTime _defaultStart() {
    final DateTime now = DateTime.now();
    return DateTime(now.year, now.month, now.day, 19);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_slotApplied && b == null) {
      _slotApplied = true;
      _minutes =
          PosScope.of(context)
              .session
              .bootstrap
              ?.branch
              .settings
              .bookingSlotMinutes ??
          300;
    }
  }

  @override
  void dispose() {
    for (final TextEditingController t in <TextEditingController>[
      _name,
      _phone,
      _minSpend,
      _note,
    ]) {
      t.dispose();
    }
    super.dispose();
  }

  DateTime get _end => _start.add(Duration(minutes: _minutes));

  Future<void> _pickDate() async {
    final DateTime? d = await showDatePicker(
      context: context,
      initialDate: _start,
      firstDate: DateTime.now().subtract(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (d != null) {
      setState(
        () => _start = DateTime(
          d.year,
          d.month,
          d.day,
          _start.hour,
          _start.minute,
        ),
      );
    }
  }

  Future<void> _pickTime() async {
    final TimeOfDay? t = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_start),
    );
    if (t != null) {
      setState(
        () => _start = DateTime(
          _start.year,
          _start.month,
          _start.day,
          t.hour,
          t.minute,
        ),
      );
    }
  }

  Future<void> _addItem() async {
    final PreorderDraft? d = await showPreorderPicker(context, start: _start);
    if (d != null) setState(() => _items.add(d));
  }

  Future<void> _save() async {
    if (_room == null) {
      setState(() => _error = 'Xonani tanlang.');
      return;
    }
    if (_name.text.trim().isEmpty && _phone.text.trim().isEmpty) {
      setState(() => _error = 'Mijozning ismi yoki telefonini yozing.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    final PosScope scope = PosScope.of(context);
    final PosBookingInput input = PosBookingInput(
      diningTableId: _room,
      extraTableIds: _extra.isEmpty && b == null ? null : _extra.toList(),
      startsAt: _start,
      endsAt: _end,
      guestsPlanned: _guests,
      eventType: _event,
      contactName: _name.text.trim().isEmpty ? null : _name.text.trim(),
      contactPhone: _phone.text.trim().isEmpty ? null : _phone.text.trim(),
      source: _source,
      minSpend: parseMoney(_minSpend.text),
      note: _note.text.trim().isEmpty ? null : _note.text.trim(),
      confirm: b == null ? _confirm : null,
    );
    try {
      PosBooking saved = b == null
          ? await scope.data.bookings.create(input)
          : await scope.data.bookings.update(
              b!.uuid,
              input,
              version: b!.version,
            );
      for (final PreorderDraft d in _items) {
        saved = await scope.data.bookings.addItem(
          saved.uuid,
          productVariantId: d.variant.id,
          quantity: d.quantity,
          dueAt: d.dueAt,
        );
      }
      if (mounted) Navigator.pop(context, saved);
    } on PosConflict catch (e) {
      if (mounted) setState(() => _error = _conflictText(e));
    } on PosValidationException catch (e) {
      if (mounted) setState(() => _error = e.firstError);
    } on Object catch (e) {
      if (mounted) showPosError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _conflictText(PosConflict e) {
    if (e.reason != 'booking_conflict') return e.message;
    final List<Object?> list =
        (e.data['conflicts'] as List<Object?>?) ?? const <Object?>[];
    final List<String> times = <String>[
      for (final Object? x in list)
        if (x is Map)
          '${hm(DateTime.parse('${x['starts_at']}'))}–${hm(DateTime.parse('${x['ends_at']}'))}',
    ];
    return times.isEmpty
        ? e.message
        : '${e.message} Band: ${times.join(', ')}.';
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Scaffold(
      backgroundColor: c.page,
      appBar: AppBar(
        title: Text(b == null ? 'Yangi bron' : 'Bronni tahrirlash'),
        actions: <Widget>[
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: RzButton(
              key: const ValueKey<String>('booking-save'),
              label: 'Saqlash',
              icon: Icons.check_rounded,
              loading: _busy,
              onPressed: _save,
            ),
          ),
        ],
      ),
      body: LayoutBuilder(
        builder: (BuildContext context, BoxConstraints box) {
          final Widget main = _fields(c);
          final Widget side = _preorder(c);
          if (box.maxWidth < 900) {
            return ListView(
              padding: const EdgeInsets.all(16),
              children: <Widget>[main, const SizedBox(height: 16), side],
            );
          }
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Expanded(
                flex: 6,
                child: ListView(
                  padding: const EdgeInsets.all(16),
                  children: <Widget>[main],
                ),
              ),
              Expanded(
                flex: 4,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(0, 16, 16, 16),
                  children: <Widget>[side],
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _label(RzColors c, String t) => Padding(
    padding: const EdgeInsets.only(top: 14, bottom: 6),
    child: Text(t, style: RzType.footnote.copyWith(color: c.fgMuted)),
  );

  Widget _fields(RzColors c) => PosCard(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (_error != null) ...<Widget>[
          NoticeBanner(
            message: _error!,
            tone: StatusTone.danger,
            icon: Icons.event_busy_rounded,
          ),
          const SizedBox(height: 8),
        ],
        _label(c, 'Xona yoki joy'),
        ChoiceRow<int>(
          options: <int, String>{
            for (final PosCalendarRoom r in widget.rooms) r.id: r.name,
          },
          selected: _room,
          onSelected: (int id) => setState(() {
            _room = id;
            _extra.remove(id);
          }),
        ),
        _label(c, "Banket: qo'shimcha xonalar"),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: <Widget>[
            for (final PosCalendarRoom r in widget.rooms)
              if (r.id != _room && r.kind == 'room')
                FilterChip(
                  label: Text(r.name),
                  selected: _extra.contains(r.id),
                  materialTapTargetSize: MaterialTapTargetSize.padded,
                  onSelected: (bool v) => setState(
                    () => v ? _extra.add(r.id) : _extra.remove(r.id),
                  ),
                ),
          ],
        ),
        _label(c, 'Sana va vaqt'),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: <Widget>[
            OutlinedButton.icon(
              onPressed: _pickDate,
              icon: const Icon(Icons.calendar_today_outlined),
              label: Text(longDay(_start)),
            ),
            OutlinedButton.icon(
              onPressed: _pickTime,
              icon: const Icon(Icons.schedule_rounded),
              label: Text('${hm(_start)} – ${hm(_end)}'),
            ),
            for (final int h in <int>[12, 13, 18, 19, 20])
              ActionChip(
                label: Text('$h:00'),
                materialTapTargetSize: MaterialTapTargetSize.padded,
                onPressed: () => setState(
                  () => _start = DateTime(
                    _start.year,
                    _start.month,
                    _start.day,
                    h,
                  ),
                ),
              ),
          ],
        ),
        _label(c, 'Davomiyligi'),
        ChoiceRow<int>(
          options: <int, String>{
            for (final int m in <int>{
              180,
              240,
              300,
              360,
              _minutes,
            }.toList()..sort())
              m: minutesLabel(m),
          },
          selected: _minutes,
          onSelected: (int m) => setState(() => _minutes = m),
        ),
        _label(c, 'Mehmonlar'),
        Row(
          children: <Widget>[
            PosQuantityStepper(
              value: PosQuantity.whole(_guests),
              max: const PosQuantity.whole(300),
              onChanged: (PosQuantity q) =>
                  setState(() => _guests = q.milli ~/ PosQuantity.scaleFactor),
            ),
            const SizedBox(width: 12),
            Text(
              '$_guests kishi',
              style: RzType.headline.copyWith(color: c.fg),
            ),
          ],
        ),
        _label(c, 'Tadbir'),
        ChoiceRow<String>(
          options: eventTypes,
          selected: _event,
          onSelected: (String v) => setState(() => _event = v),
        ),
        const SizedBox(height: 14),
        Row(
          children: <Widget>[
            Expanded(
              child: TextField(
                key: const ValueKey<String>('booking-name'),
                controller: _name,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(labelText: 'Mijoz ismi'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: TextField(
                key: const ValueKey<String>('booking-phone'),
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Telefon',
                  hintText: '90 123 45 67',
                ),
              ),
            ),
          ],
        ),
        _label(c, 'Qayerdan'),
        ChoiceRow<String>(
          options: bookingSources,
          selected: _source,
          onSelected: (String v) => setState(() => _source = v),
        ),
        const SizedBox(height: 14),
        MoneyField(controller: _minSpend, label: 'Minimal summa (ixtiyoriy)'),
        const SizedBox(height: 12),
        TextField(
          controller: _note,
          decoration: const InputDecoration(labelText: 'Izoh'),
        ),
        if (b == null)
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: _confirm,
            title: const Text('Darhol tasdiqlangan'),
            subtitle: const Text("O'chirilsa — so'rov (keyin tasdiqlanadi)"),
            onChanged: (bool v) => setState(() => _confirm = v),
          ),
      ],
    ),
  );

  Widget _preorder(RzColors c) => PosCard(
    title: 'Oldindan buyurtma',
    trailing: b == null
        ? TextButton.icon(
            key: const ValueKey<String>('preorder-open'),
            onPressed: _addItem,
            icon: const Icon(Icons.add_rounded),
            label: const Text("Taom qo'shish"),
          )
        : null,
    child: b != null
        ? Text(
            "Oldindan buyurtmani bron sahifasida o'zgartiring.",
            style: RzType.body.copyWith(color: c.fgMuted),
          )
        : Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              if (_items.isEmpty)
                Text(
                  "Osh, qozon kabob yoki set oldindan buyurilsa, oshxona uni vaqtida tayyorlaydi.",
                  style: RzType.body.copyWith(color: c.fgMuted),
                ),
              for (var i = 0; i < _items.length; i++)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(_items[i].name),
                  subtitle: Text(
                    '${_items[i].quantity.label(_items[i].variant.unit, unitLabel: _items[i].variant.unitLabel)}'
                    ' · tayyor ${_items[i].dueAt == null ? hm(_start) : hm(_items[i].dueAt!)}',
                  ),
                  trailing: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      Text(_items[i].amount.formatAmount()),
                      IconButton(
                        tooltip: 'Olib tashlash',
                        icon: const Icon(Icons.delete_outline_rounded),
                        onPressed: () => setState(() => _items.removeAt(i)),
                      ),
                    ],
                  ),
                ),
              if (_items.isNotEmpty) ...<Widget>[
                const Divider(height: 20),
                AmountRow(
                  label: 'Oldindan buyurtma jami',
                  amount: PosMoney.sum(
                    _items.map((PreorderDraft d) => d.amount),
                  ),
                  bold: true,
                ),
              ],
            ],
          ),
  );
}
