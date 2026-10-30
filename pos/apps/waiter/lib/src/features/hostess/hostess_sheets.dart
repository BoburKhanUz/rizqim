import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../common/guests_stepper.dart';
import '../../common/modules.dart';
import '../../common/sheet.dart';

/// Hostes aytadigan kutish vaqtlari (daqiqa).
const List<int> quotedWaits = <int>[10, 15, 20, 30, 45, 60];

/// Kutish ro'yxatiga yangi mehmon.
class WaitlistDraft {
  const WaitlistDraft({
    required this.name,
    required this.partySize,
    this.phone,
    this.quotedWaitMinutes,
    this.note,
  });

  final String name;
  final int partySize;
  final String? phone;
  final int? quotedWaitMinutes;
  final String? note;
}

/// "Mehmon yozish": ism, telefon, necha kishi, aytilgan kutish, izoh. Yopilsa `null`.
Future<WaitlistDraft?> showAddGuestSheet(BuildContext context) =>
    showWaiterSheet<WaitlistDraft>(
      context,
      fullHeight: true,
      builder: (BuildContext context) => const _AddGuestSheet(),
    );

class _AddGuestSheet extends StatefulWidget {
  const _AddGuestSheet();

  @override
  State<_AddGuestSheet> createState() => _AddGuestSheetState();
}

class _AddGuestSheetState extends State<_AddGuestSheet> {
  final TextEditingController _name = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _note = TextEditingController();
  int _party = 2;
  int? _quoted = 15;

  @override
  void initState() {
    super.initState();
    _name.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool ready = _name.text.trim().isNotEmpty;
    return SafeArea(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const Padding(
            padding: EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: SheetHeader(
              title: 'Mehmon yozish',
              subtitle: "Stol bo'shasa chaqirasiz.",
            ),
          ),
          Expanded(
            // A short form: built at once (a lazy list would drop the fields below the fold)
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  TextField(
                    controller: _name,
                    autofocus: true,
                    maxLength: 100,
                    textCapitalization: TextCapitalization.words,
                    decoration: const InputDecoration(
                      labelText: 'Ismi',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  TextField(
                    controller: _phone,
                    keyboardType: TextInputType.phone,
                    maxLength: 20,
                    decoration: const InputDecoration(
                      labelText: 'Telefon (ixtiyoriy)',
                      hintText: '+998 90 123 45 67',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Necha kishi',
                    style: RzType.body.copyWith(color: c.fgMuted),
                  ),
                  GuestsStepper(
                    value: _party,
                    max: 200,
                    onChanged: (int v) => setState(() => _party = v),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Aytilgan kutish',
                    style: RzType.body.copyWith(color: c.fgMuted),
                  ),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    children: <Widget>[
                      for (final int m in quotedWaits)
                        ChoiceChip(
                          label: Text('$m daq'),
                          selected: _quoted == m,
                          materialTapTargetSize: MaterialTapTargetSize.padded,
                          onSelected: (bool on) =>
                              setState(() => _quoted = on ? m : null),
                        ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _note,
                    maxLength: 255,
                    textCapitalization: TextCapitalization.sentences,
                    decoration: const InputDecoration(
                      labelText: 'Izoh (ixtiyoriy)',
                      hintText: 'Masalan: deraza yonida, bolalar stuli',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: RzButton(
              label: "Ro'yxatga yozish",
              icon: Icons.person_add_alt_1_outlined,
              size: RzButtonSize.lg,
              fullWidth: true,
              onPressed: ready
                  ? () => Navigator.of(context).pop(
                      WaitlistDraft(
                        name: _name.text.trim(),
                        partySize: _party,
                        phone: _clean(_phone.text),
                        quotedWaitMinutes: _quoted,
                        note: _clean(_note.text),
                      ),
                    )
                  : null,
            ),
          ),
        ],
      ),
    );
  }
}

String? _clean(String s) => s.trim().isEmpty ? null : s.trim();

/// Joylashtirish tanlovi: stol, buyurtma ham ochilsinmi, kimning nomiga.
class SeatChoice {
  const SeatChoice({
    required this.table,
    this.openOrder = false,
    this.waiterId,
  });

  final FloorTable table;
  final bool openOrder;
  final int? waiterId;
}

/// "Joylashtirish": bo'sh stollar (zallar bo'yicha; sig'adiganlari oldin, tozalanayotgani belgisi bilan — joylashtirishga
/// to'sqinlik qilmaydi). [canOpenOrder] — buyurtma ochish huquqi (`orders.manage`). Yopilsa `null`.
Future<SeatChoice?> showSeatSheet(
  BuildContext context, {
  required PosWaitlistEntry entry,
  required PosFloor floor,
  required bool canOpenOrder,
  required List<PosStaffRef> waiters,
  int? me,
}) => showWaiterSheet<SeatChoice>(
  context,
  fullHeight: true,
  builder: (BuildContext context) => _SeatSheet(
    entry: entry,
    floor: floor,
    canOpenOrder: canOpenOrder,
    waiters: waiters,
    me: me,
  ),
);

class _SeatSheet extends StatefulWidget {
  const _SeatSheet({
    required this.entry,
    required this.floor,
    required this.canOpenOrder,
    required this.waiters,
    this.me,
  });

  final PosWaitlistEntry entry;
  final PosFloor floor;
  final bool canOpenOrder;
  final List<PosStaffRef> waiters;
  final int? me;

  @override
  State<_SeatSheet> createState() => _SeatSheetState();
}

class _SeatSheetState extends State<_SeatSheet> {
  FloorTable? _table;
  bool _open = false;
  late int? _waiter = widget.waiters.any((PosStaffRef w) => w.id == widget.me)
      ? widget.me
      : null;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final int party = widget.entry.partySize;
    final List<Widget> rows = <Widget>[];
    final List<FloorHall> halls = <FloorHall>[...widget.floor.halls]
      ..sort((FloorHall a, FloorHall b) => a.sortOrder.compareTo(b.sortOrder));
    for (final FloorHall h in halls) {
      if (!h.isActive) continue;
      final List<FloorTable> free =
          <FloorTable>[
            for (final FloorTable t in h.tables)
              if (t.isActive && t.orders.isEmpty) t,
          ]..sort((FloorTable a, FloorTable b) {
            // Fitting, ready tables first
            int rank(FloorTable t) =>
                (t.isCleaning ? 2 : 0) + ((t.seats ?? party) < party ? 1 : 0);
            return rank(a).compareTo(rank(b));
          });
      if (free.isEmpty) continue;
      rows.add(
        Padding(
          padding: const EdgeInsets.fromLTRB(4, 12, 4, 6),
          child: Text(
            h.name,
            style: RzType.headline.copyWith(color: c.fgMuted),
          ),
        ),
      );
      for (final FloorTable t in free) {
        final bool picked = _table?.id == t.id;
        final bool small = t.seats != null && t.seats! < party;
        rows.add(
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Material(
              color: picked
                  ? c.brandSoft
                  : Color.alphaBlend(c.surface2, c.surface),
              borderRadius: BorderRadius.circular(RzRadius.control),
              child: InkWell(
                borderRadius: BorderRadius.circular(RzRadius.control),
                onTap: () => setState(() => _table = t),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(minHeight: 56),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    child: Row(
                      children: <Widget>[
                        Icon(
                          picked
                              ? Icons.radio_button_checked
                              : Icons.table_restaurant_outlined,
                          color: picked ? c.brandSoftFg : c.fg,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                t.name,
                                style: RzType.headline.copyWith(color: c.fg),
                              ),
                              if (t.isCleaning || t.reservation != null)
                                Text(
                                  t.isCleaning
                                      ? 'Tozalanmoqda'
                                      : 'Bron: ${_time(t.reservation!.startsAt)}',
                                  style: RzType.footnote.copyWith(
                                    color: c.warningSoftFg,
                                  ),
                                ),
                            ],
                          ),
                        ),
                        if (t.seats != null)
                          Text(
                            "${t.seats} o'rin",
                            style: RzType.body.copyWith(
                              color: small ? c.dangerSoftFg : c.fgMuted,
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      }
    }
    return SafeArea(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: SheetHeader(
              title: 'Joylashtirish',
              subtitle: '${widget.entry.guestName} · $party kishi',
            ),
          ),
          Expanded(
            child: rows.isEmpty
                ? const EmptyState(
                    icon: Icons.event_busy_outlined,
                    title: "Bo'sh stol yo'q",
                    message: "Stol bo'shaganda zal qayta yuklanadi.",
                  )
                : ListView(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                    children: rows,
                  ),
          ),
          if (widget.canOpenOrder) ...<Widget>[
            SwitchListTile(
              value: _open,
              onChanged: (bool v) => setState(() => _open = v),
              title: const Text('Buyurtmani ham ochish'),
              subtitle: Text('$party mehmon bilan'),
            ),
            if (_open && widget.waiters.isNotEmpty)
              SizedBox(
                height: 52,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  children: <Widget>[
                    for (final PosStaffRef w in widget.waiters)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        child: ChoiceChip(
                          label: Text(
                            w.id == widget.me ? '${w.name} (men)' : w.name,
                          ),
                          selected: _waiter == w.id,
                          materialTapTargetSize: MaterialTapTargetSize.padded,
                          onSelected: (bool on) =>
                              setState(() => _waiter = on ? w.id : null),
                        ),
                      ),
                  ],
                ),
              ),
          ],
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: RzButton(
              label: _table == null
                  ? 'Stolni tanlang'
                  : '${_table!.name}ga joylashtirish',
              icon: Icons.event_seat_outlined,
              size: RzButtonSize.lg,
              fullWidth: true,
              onPressed: _table == null
                  ? null
                  : () => Navigator.of(context).pop(
                      SeatChoice(
                        table: _table!,
                        openOrder: widget.canOpenOrder && _open,
                        waiterId: widget.canOpenOrder && _open ? _waiter : null,
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

String _time(DateTime? at) => at == null ? '' : formatReceiptTime(at.toLocal());

/// P6-26: hostes stolidan bron (ega va menejer "yo'lda kelayotgan" mehmonni oldindan vaqt qoidasisiz yozadi —
/// `skip_lead_time`).
class HostessBookingInput extends PosBookingInput {
  const HostessBookingInput({
    super.diningTableId,
    super.startsAt,
    super.guestsPlanned,
    super.contactName,
    super.contactPhone,
    super.source,
    super.note,
    this.skipLeadTime = false,
  });

  /// Filialning "kamida N daqiqa oldin" qoidasini chetlab o'tish (faqat ega va menejerda amal qiladi).
  final bool skipLeadTime;

  @override
  Map<String, Object?> toJson() => <String, Object?>{
    ...super.toJson(),
    if (skipLeadTime) 'skip_lead_time': true,
  };
}

/// "Bron qo'shish": mehmon, telefon, necha kishi, stol, qachon (hozir yoki N daqiqadan keyin). [mayOverride] — ega
/// yoki menejer: "yo'lda kelayotgan mehmon" belgisi. Yopilsa `null`.
Future<HostessBookingInput?> showBookingSheet(
  BuildContext context, {
  required List<PosTable> tables,
  required DateTime now,
  required bool mayOverride,
  PosBookingRules? rules,
}) => showWaiterSheet<HostessBookingInput>(
  context,
  fullHeight: true,
  builder: (BuildContext context) => _BookingSheet(
    tables: tables,
    now: now,
    mayOverride: mayOverride,
    rules: rules,
  ),
);

class _BookingSheet extends StatefulWidget {
  const _BookingSheet({
    required this.tables,
    required this.now,
    required this.mayOverride,
    this.rules,
  });

  final List<PosTable> tables;
  final DateTime now;
  final bool mayOverride;
  final PosBookingRules? rules;

  @override
  State<_BookingSheet> createState() => _BookingSheetState();
}

class _BookingSheetState extends State<_BookingSheet> {
  final TextEditingController _name = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  int _guests = 2;
  int? _tableId;
  int _inMinutes = 60;
  bool _walkIn = false;

  static const List<int> _starts = <int>[0, 15, 30, 60, 120, 180];

  @override
  void initState() {
    super.initState();
    _name.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final int lead = widget.rules?.tableMinAdvanceMinutes ?? 0;
    final bool tooSoon = !_walkIn && _inMinutes < lead;
    final bool ready =
        _name.text.trim().isNotEmpty && _tableId != null && !tooSoon;
    final PosMoney? deposit = depositFor(
      widget.rules,
      guests: _guests,
      isTable: true,
    );
    return SafeArea(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const Padding(
            padding: EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: SheetHeader(title: "Bron qo'shish"),
          ),
          Expanded(
            // A short form: built at once (a lazy list would drop the fields below the fold)
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  TextField(
                    controller: _name,
                    maxLength: 100,
                    textCapitalization: TextCapitalization.words,
                    decoration: const InputDecoration(
                      labelText: 'Mehmon ismi',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  TextField(
                    controller: _phone,
                    keyboardType: TextInputType.phone,
                    maxLength: 20,
                    decoration: const InputDecoration(
                      labelText: 'Telefon (ixtiyoriy)',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  Text(
                    'Necha kishi',
                    style: RzType.body.copyWith(color: c.fgMuted),
                  ),
                  GuestsStepper(
                    value: _guests,
                    max: 200,
                    onChanged: (int v) => setState(() => _guests = v),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    initialValue: _tableId,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Stol',
                      border: OutlineInputBorder(),
                    ),
                    items: <DropdownMenuItem<int>>[
                      for (final PosTable t in widget.tables)
                        DropdownMenuItem<int>(
                          value: t.id,
                          child: Text(
                            t.seats == null
                                ? t.name
                                : "${t.name} · ${t.seats} o'rin",
                          ),
                        ),
                    ],
                    onChanged: (int? v) => setState(() => _tableId = v),
                  ),
                  const SizedBox(height: 12),
                  Text('Qachon', style: RzType.body.copyWith(color: c.fgMuted)),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    children: <Widget>[
                      for (final int m in _starts)
                        ChoiceChip(
                          label: Text(
                            m == 0
                                ? 'Hozir'
                                : '${formatReceiptTime(widget.now.add(Duration(minutes: m)).toLocal())} ($m daq)',
                          ),
                          selected: _inMinutes == m,
                          materialTapTargetSize: MaterialTapTargetSize.padded,
                          onSelected: (_) => setState(() => _inMinutes = m),
                        ),
                    ],
                  ),
                  if (widget.mayOverride)
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      value: _walkIn,
                      onChanged: (bool v) => setState(() => _walkIn = v),
                      title: const Text("Yo'lda kelayotgan mehmon"),
                      subtitle: Text(
                        lead > 0
                            ? 'Kamida $lead daqiqa oldin qoidasi qo\'llanmaydi (ega va menejer).'
                            : "Oldindan vaqt qoidasi qo'llanmaydi (ega va menejer).",
                      ),
                    ),
                  if (tooSoon)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(
                        "Stol bronini kamida $lead daqiqa oldin yozish mumkin.",
                        style: RzType.footnote.copyWith(color: c.dangerSoftFg),
                      ),
                    ),
                  if (deposit != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(
                        "Bo'nak kerak: ${deposit.format()} (kassada olinadi)"
                        '${widget.rules!.depositRequiredToConfirm ? ": bo'naksiz bron tasdiqlanmaydi." : '.'}',
                        style: RzType.footnote.copyWith(color: c.warningSoftFg),
                      ),
                    ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: RzButton(
              label: 'Bron qilish',
              icon: Icons.event_available_outlined,
              size: RzButtonSize.lg,
              fullWidth: true,
              onPressed: ready
                  ? () => Navigator.of(context).pop(
                      HostessBookingInput(
                        diningTableId: _tableId,
                        // A minute ahead for "now": the server must not see a start in the past
                        startsAt: widget.now.add(
                          Duration(minutes: _inMinutes == 0 ? 1 : _inMinutes),
                        ),
                        guestsPlanned: _guests,
                        contactName: _name.text.trim(),
                        contactPhone: _clean(_phone.text),
                        source: _walkIn ? 'walk_in' : 'phone',
                        skipLeadTime: _walkIn,
                      ),
                    )
                  : null,
            ),
          ),
        ],
      ),
    );
  }
}

/// Stol bronining qoidadagi bo'nagi (backend `BookingRules::depositRequired` kabi): qoida yo'q, o'chiq, joy stol emas
/// yoki mehmonlar kam — `null`.
PosMoney? depositFor(
  PosBookingRules? rules, {
  required int? guests,
  required bool isTable,
}) {
  if (rules == null || !rules.asksDeposit || !isTable) return null;
  final int n = (guests ?? 1) < 1 ? 1 : (guests ?? 1);
  if (n < rules.depositFromGuests) return null;
  return rules.depositMode == 'per_guest'
      ? rules.depositAmount * n
      : rules.depositAmount;
}

/// Bootstrap'dagi stol turi (bo'nak faqat `table` joylarga).
bool isTableKind(PosSession session, int? tableId) {
  for (final PosTable t in session.bootstrap?.tables ?? const <PosTable>[]) {
    if (t.id == tableId) return t.kind == FloorTableKind.table;
  }
  return false;
}

/// Ega va menejer "yo'lda kelayotgan" mehmonni yozadi (backend `BookingRules::mayOverride`).
bool mayOverrideLeadTime(PosSession session) =>
    session.staffRole == StaffRoles.owner ||
    session.staffRole == StaffRoles.manager;
