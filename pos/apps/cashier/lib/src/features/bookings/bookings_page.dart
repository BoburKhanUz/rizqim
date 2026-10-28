import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/plan_gate.dart';
import '../../shell/pos_scope.dart';
import '../../shell/routes.dart';
import '../billing/cashier_ui.dart';
import 'booking_detail_page.dart';
import 'booking_form_page.dart';

/// Bron taqvimi (POS_SPEC 6.1; 13-ekran 15): kun bo'yicha xona × vaqt jadvali (bugun va keyingi kunlar), telefon
/// bo'yicha qidiruv, yangi bron (bo'sh katakni bosish — o'sha xona va soat), bron sahifasi (avans, oldindan buyurtma,
/// "Keldi"). `booking.changed` kelganda yangilanadi.
class BookingsPage extends StatefulWidget {
  const BookingsPage({super.key});

  @override
  State<BookingsPage> createState() => _BookingsPageState();
}

class _BookingsPageState extends State<BookingsPage> with RefreshingState {
  late DateTime _day;
  PosBookingCalendar? _calendar;
  PosException? _error;
  bool _started = false;
  final TextEditingController _phone = TextEditingController();
  List<PosBooking>? _found;

  /// Jadval: 08:00 dan keyingi kun 02:00 gacha.
  static const int _firstHour = 8;
  static const int _hours = 18;
  static const double _hourWidth = 72;
  static const double _rowHeight = 64;
  static const double _labelWidth = 132;

  PosScope get _scope => PosScope.of(context);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      final DateTime now = _scope.services.now().toLocal();
      _day = DateTime(now.year, now.month, now.day);
      startRefreshing(
        _scope.realtime,
        filter: (PosEvent e) => e is PosBookingChanged,
        pollInterval: const Duration(seconds: 60),
      );
      unawaited(refresh());
    }
  }

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  @override
  Future<void> refresh() async {
    final DateTime day = _day;
    try {
      // Kechasi 02:00 gacha davom etadigan bronlar uchun ertangi kun ham olinadi
      final PosBookingCalendar cal = await _scope.data.bookings.calendar(
        from: isoDay(day),
        to: isoDay(day.add(const Duration(days: 1))),
      );
      if (!mounted || day != _day) return;
      setState(() {
        _calendar = cal;
        _error = null;
      });
    } on PosException catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  void _setDay(DateTime d) {
    setState(() {
      _day = DateTime(d.year, d.month, d.day);
      _calendar = null;
      _found = null;
    });
    unawaited(refresh());
  }

  Future<void> _search() async {
    final String q = _phone.text.replaceAll(RegExp(r'\D'), '');
    if (q.length < 4) {
      showPosMessage(context, 'Telefondan kamida 4 raqam yozing.');
      return;
    }
    await runGuarded(context, () async {
      final List<PosBooking> list = await _scope.data.bookings.list(phone: q);
      if (mounted) setState(() => _found = list);
    });
  }

  List<PosCalendarRoom> get _rooms =>
      _calendar?.rooms ?? const <PosCalendarRoom>[];

  Future<void> _create({int? roomId, DateTime? start}) async {
    if (!ensurePlan(context, PosFeature.bookings)) return;
    final PosBooking? b = await CashierRoutes.push<PosBooking>(
      context,
      BookingFormPage(
        rooms: _rooms,
        roomId: roomId,
        start: start ?? DateTime(_day.year, _day.month, _day.day, 19),
      ),
    );
    if (b == null || !mounted) return;
    showPosMessage(
      context,
      'Bron saqlandi: ${b.contactName ?? ''} ${hm(b.startsAt)}',
    );
    await refresh();
    if (mounted) await _open(b);
  }

  Future<void> _open(PosBooking b) async {
    await CashierRoutes.push<void>(
      context,
      BookingDetailPage(booking: b, rooms: _rooms),
    );
    if (mounted) await refresh();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Material(
      color: c.page,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          _toolbar(c),
          Expanded(child: _content(c)),
        ],
      ),
    );
  }

  Widget _toolbar(RzColors c) {
    final DateTime today = _scope.services.now().toLocal();
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: <Widget>[
          IconButton(
            tooltip: 'Oldingi kun',
            icon: const Icon(Icons.chevron_left_rounded),
            onPressed: () => _setDay(_day.subtract(const Duration(days: 1))),
          ),
          for (var i = 0; i < 7; i++)
            Builder(
              builder: (BuildContext context) {
                final DateTime d = DateTime(
                  today.year,
                  today.month,
                  today.day + i,
                );
                return ChoiceChip(
                  label: Text(
                    i == 0
                        ? 'Bugun'
                        : (i == 1 ? 'Ertaga' : '${_weekday(d)} ${dm(d)}'),
                  ),
                  selected: d == _day,
                  materialTapTargetSize: MaterialTapTargetSize.padded,
                  onSelected: (_) => _setDay(d),
                );
              },
            ),
          IconButton(
            tooltip: 'Keyingi kun',
            icon: const Icon(Icons.chevron_right_rounded),
            onPressed: () => _setDay(_day.add(const Duration(days: 1))),
          ),
          const SizedBox(width: 8),
          SizedBox(
            width: 220,
            child: TextField(
              key: const ValueKey<String>('booking-phone-search'),
              controller: _phone,
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.search,
              decoration: const InputDecoration(
                labelText: 'Telefon bo\'yicha',
                prefixIcon: Icon(Icons.search_rounded),
                isDense: true,
              ),
              onSubmitted: (_) => unawaited(_search()),
            ),
          ),
          // P4-21: a new booking is the plan's bookings; the calendar and the bookings that exist stay open
          PlanButton(
            key: const ValueKey<String>('booking-new'),
            feature: PosFeature.bookings,
            label: 'Yangi bron',
            icon: Icons.add_rounded,
            onPressed: _calendar == null ? null : () => _create(),
          ),
        ],
      ),
    );
  }

  static String _weekday(DateTime d) =>
      const <String>['Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh', 'Ya'][d.weekday - 1];

  Widget _content(RzColors c) {
    final PosBookingCalendar? cal = _calendar;
    if (cal == null) {
      if (_error != null) {
        return Padding(
          padding: const EdgeInsets.all(24),
          child: InlineError(
            message: _error!.isNetworkError
                ? "Aloqa yo'q: bronlarni yuklab bo'lmadi."
                : _error!.message,
            onRetry: () => unawaited(refresh()),
          ),
        );
      }
      return const LoadingBox();
    }
    final List<PosBooking> dayList = _dayBookings(cal);
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      children: <Widget>[
        if (_found != null) ...<Widget>[
          _foundCard(c),
          const SizedBox(height: 12),
        ],
        Text(longDay(_day), style: RzType.title.copyWith(color: c.fg)),
        const SizedBox(height: 8),
        PosCard(padding: const EdgeInsets.all(8), child: _grid(c, cal)),
        if (cal.unassigned.isNotEmpty) ...<Widget>[
          const SizedBox(height: 12),
          PosCard(
            title: 'Xonasiz bronlar',
            child: Column(
              children: <Widget>[
                for (final PosBooking b in cal.unassigned) _row(c, b),
              ],
            ),
          ),
        ],
        const SizedBox(height: 12),
        PosCard(
          title: 'Shu kungi bronlar (${dayList.length})',
          child: dayList.isEmpty
              ? Text(
                  "Bron yo'q. Bo'sh katakni bosib bron qiling.",
                  style: RzType.body.copyWith(color: c.fgMuted),
                )
              : Column(
                  children: <Widget>[
                    for (final PosBooking b in dayList) _row(c, b),
                  ],
                ),
        ),
      ],
    );
  }

  List<PosBooking> _dayBookings(PosBookingCalendar cal) {
    final DateTime start = _day.add(const Duration(hours: 5));
    final DateTime end = start.add(const Duration(days: 1));
    final Map<String, PosBooking> all = <String, PosBooking>{
      for (final PosCalendarRoom r in cal.rooms)
        for (final PosBooking b in r.bookings) b.uuid: b,
      for (final PosBooking b in cal.unassigned) b.uuid: b,
    };
    return all.values.where((PosBooking b) {
        final DateTime s = b.startsAt.toLocal();
        return !s.isBefore(start) && s.isBefore(end);
      }).toList()
      ..sort((PosBooking a, PosBooking b) => a.startsAt.compareTo(b.startsAt));
  }

  Widget _grid(RzColors c, PosBookingCalendar cal) {
    final DateTime origin = _day.add(const Duration(hours: _firstHour));
    final DateTime now = _scope.services.now().toLocal();
    final double nowX = now.difference(origin).inMinutes / 60 * _hourWidth;
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: SizedBox(
        width: _labelWidth + _hours * _hourWidth,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            // Soatlar
            Row(
              children: <Widget>[
                const SizedBox(width: _labelWidth),
                for (var h = 0; h < _hours; h++)
                  SizedBox(
                    width: _hourWidth,
                    child: Text(
                      '${((_firstHour + h) % 24).toString().padLeft(2, '0')}:00',
                      style: RzType.footnote.copyWith(color: c.fgMuted),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 4),
            for (final PosCalendarRoom room in cal.rooms)
              SizedBox(
                height: _rowHeight,
                child: Row(
                  children: <Widget>[
                    SizedBox(
                      width: _labelWidth,
                      child: Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text(
                              room.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: RzType.body.copyWith(
                                color: c.fg,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            Text(
                              <String>[
                                ?room.hallName,
                                if (room.seats != null) '${room.seats} o\'rin',
                              ].join(' · '),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: RzType.caption.copyWith(color: c.fgMuted),
                            ),
                          ],
                        ),
                      ),
                    ),
                    SizedBox(
                      width: _hours * _hourWidth,
                      child: Stack(
                        children: <Widget>[
                          Row(
                            children: <Widget>[
                              for (var h = 0; h < _hours; h++)
                                _EmptySlot(
                                  key: ValueKey<String>(
                                    'slot-${room.id}-${_firstHour + h}',
                                  ),
                                  width: _hourWidth,
                                  label:
                                      '${room.name} ${(_firstHour + h) % 24}:00',
                                  onTap: () => _create(
                                    roomId: room.id,
                                    start: origin.add(Duration(hours: h)),
                                  ),
                                ),
                            ],
                          ),
                          if (nowX > 0 && nowX < _hours * _hourWidth)
                            Positioned(
                              left: nowX,
                              top: 0,
                              bottom: 0,
                              child: Container(width: 2, color: c.danger),
                            ),
                          for (final PosBooking b in room.bookings)
                            ?_block(c, b, origin),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget? _block(RzColors c, PosBooking b, DateTime origin) {
    final double left =
        b.startsAt.toLocal().difference(origin).inMinutes / 60 * _hourWidth;
    final double right =
        b.endsAt.toLocal().difference(origin).inMinutes / 60 * _hourWidth;
    const double max = _hours * _hourWidth;
    if (right <= 0 || left >= max) return null;
    final double l = left.clamp(0, max);
    final double w = (right.clamp(0, max) - l).clamp(24, max);
    final (String label, StatusTone tone, IconData icon) = bookingStatus(b);
    final (Color bg, Color fg) = toneColors(c, tone);
    return Positioned(
      left: l + 1,
      top: 4,
      bottom: 4,
      width: w - 2,
      child: Semantics(
        button: true,
        label: '${b.contactName ?? 'Bron'} ${hm(b.startsAt)}',
        child: Material(
          color: Color.alphaBlend(bg, c.surface),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(RzRadius.control),
            side: BorderSide(color: fg.withValues(alpha: 0.5)),
          ),
          child: InkWell(
            key: ValueKey<String>('booking-${b.uuid}'),
            borderRadius: BorderRadius.circular(RzRadius.control),
            onTap: () => _open(b),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Icon(icon, size: 16, color: fg),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          '${b.contactName ?? label} · ${b.guestsPlanned ?? '?'} kishi',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: RzType.footnote.copyWith(
                            color: fg,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                  Text(
                    <String>[
                      '${hm(b.startsAt)}–${hm(b.endsAt)}',
                      if (b.depositAvailable.isPositive)
                        'avans ${b.depositAvailable.formatAmount()}',
                      if (b.itemsCount > 0) '${b.itemsCount} taom',
                    ].join(' · '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: RzType.caption.copyWith(color: fg),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _row(RzColors c, PosBooking b) {
    final (String label, StatusTone tone, IconData icon) = bookingStatus(b);
    final String room = _rooms
        .where(
          (PosCalendarRoom r) =>
              b.tableIds.contains(r.id) || r.id == b.diningTableId,
        )
        .map((PosCalendarRoom r) => r.name)
        .join(' + ');
    return ListTile(
      contentPadding: EdgeInsets.zero,
      minVerticalPadding: 8,
      onTap: () => _open(b),
      leading: Text(
        hm(b.startsAt),
        style: RzType.headline.copyWith(color: c.fg),
      ),
      title: Text(
        <String>[
          b.contactName ?? 'Bron',
          if (room.isNotEmpty) room,
        ].join(' · '),
        style: RzType.body.copyWith(color: c.fg),
      ),
      subtitle: Text(
        <String>[
          '${b.guestsPlanned ?? '?'} kishi',
          ?b.eventTypeLabel,
          ?b.contactPhone,
          if (b.depositAvailable.isPositive)
            'avans ${b.depositAvailable.format()}',
        ].join(' · '),
        style: RzType.footnote.copyWith(color: c.fgMuted),
      ),
      trailing: TonePill(label: label, tone: tone, icon: icon),
    );
  }

  Widget _foundCard(RzColors c) => PosCard(
    title: 'Qidiruv: ${_phone.text}',
    trailing: IconButton(
      tooltip: 'Yopish',
      icon: const Icon(Icons.close_rounded),
      onPressed: () => setState(() => _found = null),
    ),
    child: _found!.isEmpty
        ? Text(
            'Bu raqam bilan bron topilmadi.',
            style: RzType.body.copyWith(color: c.fgMuted),
          )
        : Column(
            children: <Widget>[
              for (final PosBooking b in _found!)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  onTap: () => _open(b),
                  title: Text(
                    '${b.contactName ?? 'Bron'} · ${dm(b.startsAt, year: true)} ${hm(b.startsAt)}',
                  ),
                  subtitle: Text(
                    <String>[
                      ?b.contactPhone,
                      '${b.guestsPlanned ?? '?'} kishi',
                    ].join(' · '),
                  ),
                  trailing: TonePill(
                    label: bookingStatus(b).$1,
                    tone: bookingStatus(b).$2,
                  ),
                ),
            ],
          ),
  );
}

class _EmptySlot extends StatelessWidget {
  const _EmptySlot({
    super.key,
    required this.width,
    required this.label,
    required this.onTap,
  });

  final double width;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Semantics(
      button: true,
      label: 'Bron qilish: $label',
      child: InkWell(
        onTap: onTap,
        child: Container(
          width: width,
          decoration: BoxDecoration(
            border: Border(
              left: BorderSide(color: c.line),
              bottom: BorderSide(color: c.line),
            ),
          ),
        ),
      ),
    );
  }
}
