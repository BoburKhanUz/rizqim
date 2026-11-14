import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../counter/counter_format.dart';
import '../../shell/pos_scope.dart';
import '../../shell/routes.dart';
import '../billing/cashier_ui.dart';
import 'banquet_event_page.dart';
import 'banquet_form_page.dart';
import 'banquet_format.dart';
import 'banquet_printing.dart';

/// P6-51…P6-54: "Tadbirlar" bo'limi (modul `banquet`): tanlangan kundan 14 kunlik taqvim — kun bo'yicha, zal va vaqt
/// bilan, holat va muddati o'tgan belgilari, qoldiq; zal bo'yicha filtr, telefon bo'yicha qidiruv, yangi tadbir (zalni
/// band qilish, `banquets.manage`), bozorlik ro'yxatini chop etish. Tadbirni bosish — tadbir kartasi. Internetsiz —
/// oddiy xabar (tadbirlar faqat server bilan).
class BanquetsPage extends StatefulWidget {
  const BanquetsPage({super.key});

  @override
  State<BanquetsPage> createState() => _BanquetsPageState();
}

class _BanquetsPageState extends State<BanquetsPage> with RefreshingState {
  static const int _days = 14;

  late DateTime _from;
  PosBanquetCalendar? _calendar;
  String? _error;
  bool _started = false;
  int? _hall;
  final TextEditingController _phone = TextEditingController();
  List<PosBanquetEvent>? _found;

  PosScope get _scope => PosScope.of(context);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      final DateTime now = _scope.services.now().toLocal();
      _from = DateTime(now.year, now.month, now.day);
      startRefreshing(
        _scope.realtime,
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

  DateTime get _to => _from.add(const Duration(days: _days - 1));

  @override
  Future<void> refresh() async {
    final DateTime from = _from;
    try {
      final PosBanquetCalendar cal = await _scope.data.banquets.calendar(
        from: isoDay(from),
        to: isoDay(_to),
      );
      if (!mounted || from != _from) return;
      setState(() {
        _calendar = cal;
        _error = null;
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

  void _move(int days) {
    setState(() {
      _from = _from.add(Duration(days: days));
      _calendar = null;
      _found = null;
    });
    unawaited(refresh());
  }

  void _today() {
    final DateTime now = _scope.services.now().toLocal();
    setState(() {
      _from = DateTime(now.year, now.month, now.day);
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
      final List<PosBanquetEvent> list = await _scope.data.banquets.list(
        phone: q,
      );
      if (mounted) setState(() => _found = list);
    });
  }

  List<PosBanquetCalendarHall> get _halls =>
      _calendar?.halls ?? const <PosBanquetCalendarHall>[];

  Future<void> _open(PosBanquetEvent e) async {
    await CashierRoutes.push<void>(
      context,
      BanquetEventPage(uuid: e.uuid, halls: _halls),
    );
    if (mounted) await refresh();
  }

  Future<void> _create() async {
    final PosBanquetEvent? e = await CashierRoutes.push<PosBanquetEvent>(
      context,
      BanquetFormPage(day: _from, halls: _halls, hallId: _hall),
    );
    if (e == null || !mounted) return;
    showPosMessage(
      context,
      'Zal band qilindi: ${e.displayName}, ${banquetDate(e.eventDate)}',
    );
    await refresh();
    if (mounted) await _open(e);
  }

  Future<void> _shopping() async {
    await runGuarded(context, () async {
      final bool ok = await printBanquetShoppingList(
        context,
        from: isoDay(_from),
        to: isoDay(_to),
      );
      if (ok && mounted) {
        showPosMessage(context, "Bozorlik ro'yxati chop etildi.");
      }
    });
  }

  /// Kun → tadbirlar (birlashtirilgan zaldagi tadbir bir marta), vaqt bo'yicha.
  Map<String, List<PosBanquetEvent>> get _byDay {
    final Map<String, PosBanquetEvent> unique = <String, PosBanquetEvent>{};
    for (final PosBanquetCalendarHall h in _halls) {
      if (_hall != null && h.id != _hall) continue;
      for (final PosBanquetEvent e in h.events) {
        unique[e.uuid] = e;
      }
    }
    final List<PosBanquetEvent> all = unique.values.toList()
      ..sort(
        (PosBanquetEvent a, PosBanquetEvent b) =>
            a.startsAt.compareTo(b.startsAt),
      );
    final Map<String, List<PosBanquetEvent>> out =
        <String, List<PosBanquetEvent>>{};
    for (final PosBanquetEvent e in all) {
      (out[e.eventDate] ??= <PosBanquetEvent>[]).add(e);
    }
    return out;
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
    final PosSession session = _scope.session;
    final bool manage =
        session.can(PosPermissions.banquetsManage) &&
        session.hasModule(PosModule.banquet);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: <Widget>[
          IconButton(
            tooltip: 'Oldingi 2 hafta',
            icon: const Icon(Icons.chevron_left_rounded),
            onPressed: () => _move(-_days),
          ),
          Text(
            '${dm(_from)} – ${dm(_to, year: true)}',
            key: const ValueKey<String>('banquets-range'),
            style: RzType.headline.copyWith(color: c.fg),
          ),
          IconButton(
            tooltip: 'Keyingi 2 hafta',
            icon: const Icon(Icons.chevron_right_rounded),
            onPressed: () => _move(_days),
          ),
          TextButton(onPressed: _today, child: const Text('Bugun')),
          SizedBox(
            width: 200,
            child: TextField(
              key: const ValueKey<String>('banquets-phone'),
              controller: _phone,
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _search(),
              decoration: const InputDecoration(
                labelText: "Telefon bo'yicha",
                prefixIcon: Icon(Icons.search_rounded),
                isDense: true,
              ),
            ),
          ),
          RzButton(
            label: "Bozorlik ro'yxati",
            icon: Icons.shopping_basket_outlined,
            variant: RzButtonVariant.ghost,
            size: RzButtonSize.md,
            onPressed: _shopping,
          ),
          if (manage)
            RzButton(
              key: const ValueKey<String>('banquets-new'),
              label: 'Yangi tadbir',
              icon: Icons.add_rounded,
              size: RzButtonSize.md,
              onPressed: _calendar == null ? null : _create,
            ),
        ],
      ),
    );
  }

  Widget _content(RzColors c) {
    if (_calendar == null) {
      if (_error == null) return const LoadingBox();
      return Padding(
        padding: const EdgeInsets.all(16),
        child: Align(
          alignment: Alignment.topCenter,
          child: NoticeBanner(
            key: const ValueKey<String>('banquets-error'),
            message: _error!,
            action: TextButton(
              onPressed: refresh,
              child: const Text('Qayta urinish'),
            ),
          ),
        ),
      );
    }
    final List<PosBanquetEvent>? found = _found;
    final Map<String, List<PosBanquetEvent>> days = _byDay;
    return ListView(
      key: const ValueKey<String>('banquets-list'),
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      children: <Widget>[
        if (_halls.length > 1)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: <Widget>[
                ChoiceChip(
                  label: const Text('Hamma zallar'),
                  selected: _hall == null,
                  onSelected: (_) => setState(() => _hall = null),
                ),
                for (final PosBanquetCalendarHall h in _halls)
                  ChoiceChip(
                    key: ValueKey<String>('banquets-hall-${h.id}'),
                    label: Text(h.name),
                    selected: _hall == h.id,
                    onSelected: (_) => setState(() => _hall = h.id),
                  ),
              ],
            ),
          ),
        if (found != null) ...<Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  'Qidiruv: ${found.length} ta',
                  style: RzType.headline.copyWith(color: c.fg),
                ),
              ),
              TextButton(
                onPressed: () => setState(() => _found = null),
                child: const Text('Yopish'),
              ),
            ],
          ),
          for (final PosBanquetEvent e in found) _tile(c, e, withDate: true),
          const Divider(height: 24),
        ],
        if (days.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 32),
            child: Text(
              "Bu kunlarda tadbir yo'q.",
              key: const ValueKey<String>('banquets-empty'),
              textAlign: TextAlign.center,
              style: RzType.body.copyWith(color: c.fgMuted),
            ),
          ),
        for (final MapEntry<String, List<PosBanquetEvent>> d
            in days.entries) ...<Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(4, 12, 4, 6),
            child: Text(
              longDay(banquetDay(d.key)),
              style: RzType.headline.copyWith(color: c.fg),
            ),
          ),
          for (final PosBanquetEvent e in d.value) _tile(c, e),
        ],
      ],
    );
  }

  Widget _tile(
    RzColors c,
    PosBanquetEvent e, {
    bool withDate = false,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: PosCard(
      key: ValueKey<String>('banquet-${e.uuid}'),
      padding: EdgeInsets.zero,
      child: InkWell(
        onTap: () => _open(e),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      e.displayName,
                      style: RzType.headline.copyWith(color: c.fg),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  Text(
                    e.total.format(),
                    style: RzType.body.copyWith(
                      color: c.fg,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 2),
              Text(
                '${withDate ? '${banquetDate(e.eventDate)} · ' : ''}${banquetTime(e)} · ${banquetHalls(e)} · '
                '${e.eventTypeLabel} · ${e.guestsBilled} kishi',
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: <Widget>[
                  BanquetStatusPill(e.status),
                  if (e.overdue) const BanquetOverduePill(),
                  if (e.outstanding.isPositive &&
                      e.status != PosBanquetStatus.cancelled)
                    TonePill(
                      label: 'Qoldiq ${e.outstanding.format()}',
                      tone: StatusTone.neutral,
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
