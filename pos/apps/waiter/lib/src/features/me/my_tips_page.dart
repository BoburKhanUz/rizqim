import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../common/connection_banner.dart';
import '../../common/errors.dart';
import '../../shell/waiter_scope.dart';

/// P6-22 "Mening choy pulim": bugungi (filialning biznes kuni; terminalda smena bo'lsa — shu smena) choy pullari
/// (`GET /pos/tips`, ofitsiant faqat o'ziniki). Choy puli kassada to'lov bilan olinadi; bu sahifa faqat ko'rsatadi.
class MyTipsPage extends StatefulWidget {
  const MyTipsPage({super.key});

  @override
  State<MyTipsPage> createState() => _MyTipsPageState();
}

class _MyTipsPageState extends State<MyTipsPage> {
  PosShiftTips? _tips;
  Object? _error;
  bool _loading = false;
  StreamSubscription<PosEvent>? _events;
  bool _started = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    // A paid bill may carry a new tip: read again (the list is small)
    _events = WaiterScope.of(context).realtime?.events.listen((PosEvent e) {
      if (e is PosOrderBillChanged) unawaited(_load());
    });
    unawaited(_load());
  }

  @override
  void dispose() {
    unawaited(_events?.cancel());
    super.dispose();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() => _loading = true);
    try {
      final PosShiftTips t = await WaiterScope.of(context).data.tips
          .shiftTips();
      if (!mounted) return;
      setState(() {
        _tips = t;
        _error = null;
      });
    } on Object catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final WaiterScope scope = WaiterScope.of(context);
    return Scaffold(
      backgroundColor: c.page,
      appBar: AppBar(
        toolbarHeight: 64,
        title: const Text('Mening choy pulim'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Yangilash',
            onPressed: _loading ? null : () => unawaited(_load()),
            icon: const Icon(Icons.refresh_rounded),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: Column(
        children: <Widget>[
          ConnectionBanner(
            connectivity: scope.data.connectivity,
            onRetry: () => unawaited(_load()),
          ),
          Expanded(child: _body(context, scope)),
        ],
      ),
    );
  }

  Widget _body(BuildContext context, WaiterScope scope) {
    final RzColors c = context.rz;
    final PosShiftTips? t = _tips;
    if (t == null) {
      if (_error != null) {
        return Padding(
          padding: const EdgeInsets.all(16),
          child: Align(
            alignment: Alignment.topCenter,
            child: InlineError(
              message: describeError(_error!),
              onRetry: () => unawaited(_load()),
            ),
          ),
        );
      }
      return const Center(child: CircularProgressIndicator());
    }
    final PosTipSettings settings =
        scope.session.bootstrap?.tips ?? const PosTipSettings();
    final bool pool = settings.mode == PosTipMode.pool;
    final int? me = scope.session.staffId;
    // A waiter gets only their own share; a manager on the phone gets everyone's: keep theirs
    final List<({PosTip tip, PosMoney amount})> mine =
        <({PosTip tip, PosMoney amount})>[
          for (final PosTip x in t.tips)
            if (t.ownOnly)
              (tip: x, amount: x.amount)
            else if (_share(x, me).isPositive)
              (tip: x, amount: _share(x, me)),
        ];
    final PosMoney total = PosMoney.sum(
      mine.map((({PosTip tip, PosMoney amount}) x) => x.amount),
    );
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: <Widget>[
          Card(
            margin: EdgeInsets.zero,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    t.shiftUuid == null ? 'Bugun' : 'Shu smenada',
                    style: RzType.body.copyWith(color: c.fgMuted),
                  ),
                  const SizedBox(height: 4),
                  Semantics(
                    label: 'Choy pulim: ${total.format()}',
                    excludeSemantics: true,
                    child: Text(
                      total.format(),
                      style: RzType.display.copyWith(color: c.fg),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${mine.length} ta to\'lovdan'
                    '${t.businessDate == null ? '' : ' · ${_date(t.businessDate!)}'}',
                    style: RzType.footnote.copyWith(color: c.fgMuted),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          if (pool)
            const _Note(
              icon: Icons.groups_outlined,
              text:
                  "Filialda choy puli umumiy qozonga tushadi: ulushingiz kun oxirida menejer bo'lganda chiqadi. "
                  "Bu yerda faqat sizga to'g'ridan-to'g'ri berilgani.",
            ),
          if (pool) const SizedBox(height: 12),
          if (mine.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 24),
              child: EmptyState(
                icon: Icons.volunteer_activism_outlined,
                title: "Hali choy puli yo'q",
                message: "Kassir to'lovda choy pulini sizga yozsa, shu yerda ko'rinadi.",
              ),
            )
          else
            Card(
              margin: EdgeInsets.zero,
              child: Column(
                children: <Widget>[
                  for (var i = 0; i < mine.length; i++) ...<Widget>[
                    if (i > 0) Divider(height: 1, color: c.line),
                    _TipRow(tip: mine[i].tip, amount: mine[i].amount),
                  ],
                ],
              ),
            ),
          const SizedBox(height: 12),
          Text(
            "Choy puli chekka chiqmaydi va tushumga kirmaydi. Naqd choy pulini kassadan kassir beradi.",
            style: RzType.footnote.copyWith(color: c.fgMuted),
          ),
        ],
      ),
    );
  }

  /// Choy pulining [me] ga tegishli qismi (tuzatishdan keyin egalari bir nechta bo'lishi mumkin).
  PosMoney _share(PosTip tip, int? me) => PosMoney.sum(<PosMoney>[
    for (final PosTipHolder h in tip.holders)
      if (h.mode == PosTipMode.personal && h.staff?.id == me) h.amount,
  ]);

  /// `2026-10-30` → `30.10.2026`.
  String _date(String iso) {
    final List<String> p = iso.split('-');
    return p.length == 3 ? '${p[2]}.${p[1]}.${p[0]}' : iso;
  }
}

class _TipRow extends StatelessWidget {
  const _TipRow({required this.tip, required this.amount});

  final PosTip tip;

  /// Shu choy pulidan menga tegishli qismi.
  final PosMoney amount;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final String method = switch (tip.method) {
      'card' => 'Karta',
      'qr' => 'QR',
      _ => 'Naqd',
    };
    final String source = tip.source == 'change' ? ' · qaytimdan' : '';
    final DateTime? at = tip.createdAt;
    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 56),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(
          children: <Widget>[
            Icon(
              tip.method == 'cash'
                  ? Icons.payments_outlined
                  : Icons.credit_card_outlined,
              color: c.fgMuted,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    '$method$source',
                    style: RzType.body.copyWith(color: c.fg),
                  ),
                  if (at != null)
                    Text(
                      formatReceiptTime(at.toLocal()),
                      style: RzType.footnote.copyWith(color: c.fgMuted),
                    ),
                ],
              ),
            ),
            Text(amount.format(), style: RzType.headline.copyWith(color: c.fg)),
          ],
        ),
      ),
    );
  }
}

class _Note extends StatelessWidget {
  const _Note({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: c.infoSoft,
        borderRadius: BorderRadius.circular(RzRadius.card),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Icon(icon, color: c.infoSoftFg),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                text,
                style: RzType.body.copyWith(color: c.infoSoftFg),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
