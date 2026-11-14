import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/pos_scope.dart';

/// Ingredient to'xtatish ruxsati: kassir va menejer (`stoplist.manage`); modul `ingredient_stoplist`.
const String ingredientStopPermission = 'stoplist.manage';

/// Kassadagi "Ingredient tugadi" oynasi (menyu panelidagi tugma): ingredientlar ro'yxati; "Tugadi" — bog'langan hamma
/// taom (barcha brend va kanalda) shu filialda yopiladi; "Qayta ochish". Online: oflaynda oddiy xabar.
Future<void> showIngredientStopSheet(BuildContext context) {
  // The dialog sits on the root navigator, outside the PosScope: take what it needs now
  final PosScope scope = PosScope.of(context);
  return showDialog<void>(
    context: context,
    builder: (BuildContext context) => _IngredientStopDialog(scope: scope),
  );
}

class _IngredientStopDialog extends StatefulWidget {
  const _IngredientStopDialog({required this.scope});

  final PosScope scope;

  @override
  State<_IngredientStopDialog> createState() => _IngredientStopDialogState();
}

class _IngredientStopDialogState extends State<_IngredientStopDialog> {
  List<PosIngredient>? _items;
  String? _error;
  bool _busy = false;

  PosScope get _scope => widget.scope;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => unawaited(_load()));
  }

  Future<void> _load() async {
    try {
      final List<PosIngredient> list = await _scope.data.ingredients.list(
        branchId: _scope.session.branchId,
      );
      if (!mounted) return;
      setState(() {
        _items = list;
        _error = null;
      });
    } on PosException catch (e) {
      if (mounted) setState(() => _error = _message(e));
    }
  }

  String _message(PosException e) => e is PosNetworkException
      ? "Aloqa yo'q: ingredientlar faqat internet bilan o'zgaradi."
      : e.message;

  /// Stop-list o'zgardi: menyu tugmalari darhol yangilansin.
  Future<void> _afterChange() async {
    final int? storeId = _scope.session.bootstrap?.store.id;
    if (storeId != null) {
      try {
        await _scope.data.catalog.refreshAvailability(storeId: storeId);
      } on PosException {
        // Keyingi hodisada yangilanadi
      }
    }
    await _load();
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
      await _afterChange();
    } on PosException catch (e) {
      if (mounted) setState(() => _error = _message(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _stop(PosIngredient i) async {
    final _Choice? choice = await showDialog<_Choice>(
      context: context,
      builder: (BuildContext context) => _ReasonDialog(name: i.name),
    );
    if (choice == null || !mounted) return;
    await _run(
      () => _scope.data.ingredients.stop(
        i.id,
        branchId: _scope.session.branchId,
        reason: choice.reason,
        until: choice.until,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final List<PosIngredient>? items = _items;
    return Dialog(
      insetPadding: const EdgeInsets.all(24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 560, maxHeight: 680),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 8, 4),
              child: Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      'Ingredient tugadi',
                      style: RzType.title.copyWith(color: c.fg),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Yopish',
                    icon: const Icon(Icons.close_rounded),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Text(
                "Bosilsa shu ingredientga bog'liq hamma taom (barcha brend va kanalda) sotuvdan yopiladi.",
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
                child: Text(
                  _error!,
                  key: const ValueKey<String>('ingredient-error'),
                  style: RzType.body.copyWith(color: c.danger),
                ),
              ),
            const SizedBox(height: 8),
            Flexible(
              child: items == null
                  ? Padding(
                      padding: const EdgeInsets.all(24),
                      child: Center(
                        child: _error == null
                            ? const CircularProgressIndicator()
                            : const SizedBox.shrink(),
                      ),
                    )
                  : items.isEmpty
                  ? Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(
                        "Ingredientlar yo'q. Egasi hamkor panelida qo'shadi.",
                        style: RzType.body.copyWith(color: c.fgMuted),
                      ),
                    )
                  : ListView.separated(
                      shrinkWrap: true,
                      itemCount: items.length,
                      separatorBuilder: (_, _) =>
                          Divider(height: 1, color: c.line),
                      itemBuilder: (BuildContext context, int n) =>
                          _row(context, items[n]),
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _row(BuildContext context, PosIngredient i) {
    final RzColors c = context.rz;
    final bool stopped = i.stoppedHere;
    final PosIngredientStop? entry = i.stopped.firstOrNull;
    final String? reason = entry?.reason?.trim();
    final DateTime? until = entry?.until;
    final String sub = stopped
        ? <String>[
            'Tugagan',
            if (reason != null && reason.isNotEmpty) reason,
            if (until != null) '${formatTime(until.toLocal())} gacha',
          ].join(' · ')
        : '${i.criticalCount} ta taom yopiladi';
    return Container(
      key: ValueKey<String>('ingredient-${i.id}'),
      constraints: const BoxConstraints(minHeight: 64),
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
      child: Row(
        children: <Widget>[
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(i.name, style: RzType.headline.copyWith(color: c.fg)),
                Text(
                  sub,
                  style: RzType.footnote.copyWith(
                    color: stopped ? c.warningSoftFg : c.fgMuted,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          if (stopped)
            OutlinedButton.icon(
              icon: const Icon(Icons.lock_open_rounded),
              label: const Text('Qayta ochish'),
              onPressed: _busy
                  ? null
                  : () => unawaited(
                      _run(
                        () => _scope.data.ingredients.reopen(
                          i.id,
                          branchId: _scope.session.branchId,
                        ),
                      ),
                    ),
            )
          else
            FilledButton.icon(
              icon: const Icon(Icons.block_rounded),
              label: const Text('Tugadi'),
              style: FilledButton.styleFrom(backgroundColor: c.danger),
              onPressed: _busy ? null : () => unawaited(_stop(i)),
            ),
        ],
      ),
    );
  }
}

class _Choice {
  const _Choice(this.reason, this.until);

  final String? reason;
  final DateTime? until;
}

/// Sabab (ixtiyoriy) va muddat: qo'lda ochilguncha, 1 yoki 3 soat, bugun oxirigacha.
class _ReasonDialog extends StatefulWidget {
  const _ReasonDialog({required this.name});

  final String name;

  @override
  State<_ReasonDialog> createState() => _ReasonDialogState();
}

class _ReasonDialogState extends State<_ReasonDialog> {
  final TextEditingController _reason = TextEditingController();
  int _until = 0;

  static const List<String> _labels = <String>[
    "Qo'lda ochilguncha",
    '1 soat',
    '3 soat',
    'Bugun oxirigacha',
  ];

  DateTime? _untilValue() {
    final DateTime now = DateTime.now();
    return switch (_until) {
      1 => now.add(const Duration(hours: 1)),
      2 => now.add(const Duration(hours: 3)),
      3 => DateTime(now.year, now.month, now.day + 1),
      _ => null,
    };
  }

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text('${widget.name} tugadi'),
    content: SizedBox(
      width: 420,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          TextField(
            key: const ValueKey<String>('ingredient-reason'),
            controller: _reason,
            maxLength: 120,
            decoration: const InputDecoration(
              labelText: 'Sabab (ixtiyoriy)',
              hintText: 'Masalan, yetkazib beruvchi kechikdi',
            ),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: <Widget>[
              for (var n = 0; n < _labels.length; n++)
                ChoiceChip(
                  label: Text(_labels[n]),
                  selected: _until == n,
                  onSelected: (_) => setState(() => _until = n),
                ),
            ],
          ),
        ],
      ),
    ),
    actions: <Widget>[
      TextButton(
        onPressed: () => Navigator.of(context).pop(),
        child: const Text('Bekor qilish'),
      ),
      FilledButton(
        key: const ValueKey<String>('ingredient-confirm'),
        onPressed: () =>
            Navigator.of(context)
                .pop(_Choice(_reason.text.trim(), _untilValue())),
        child: const Text('Tugadi deb belgilash'),
      ),
    ],
  );
}
