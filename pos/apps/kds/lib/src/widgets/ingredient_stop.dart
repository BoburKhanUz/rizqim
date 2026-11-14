import 'dart:async';

import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../ingredients/kds_ingredients.dart';

/// Doska tepasidagi lenta: to'xtatilgan ingredientlar ("Tugadi: Tovuq, Piyoz") va "Ingredient tugadi" tugmasi.
/// Ruxsat yoki modul yo'q bo'lsa umuman ko'rinmaydi.
class IngredientStopBar extends StatelessWidget {
  const IngredientStopBar({
    super.key,
    required this.controller,
    required this.now,
  });

  final KdsIngredientsController controller;
  final DateTime Function() now;

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: controller,
    builder: (BuildContext context, _) {
      if (!controller.available) return const SizedBox.shrink();
      final RzColors c = context.rz;
      final List<KdsIngredient> stopped = controller.stopped;
      return Material(
        color: Color.alphaBlend(
          stopped.isEmpty ? c.surface2 : c.warningSoft,
          c.surface,
        ),
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 6, 12, 6),
          decoration: BoxDecoration(
            border: Border(bottom: BorderSide(color: c.line)),
          ),
          child: Row(
            children: <Widget>[
              Icon(
                stopped.isEmpty
                    ? Icons.inventory_2_outlined
                    : Icons.block_rounded,
                color: stopped.isEmpty ? c.fgMuted : c.warningSoftFg,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Semantics(
                  container: true,
                  liveRegion: true,
                  child: Text(
                    stopped.isEmpty
                        ? "Hamma ingredient bor"
                        : 'Tugadi: ${stopped.map((KdsIngredient i) => i.name).join(', ')}',
                    key: const ValueKey<String>('stopped-ingredients'),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: RzType.headline.copyWith(
                      color: stopped.isEmpty ? c.fgMuted : c.warningSoftFg,
                      fontWeight: stopped.isEmpty
                          ? FontWeight.w500
                          : FontWeight.w700,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              RzButton(
                key: const ValueKey<String>('ingredient-stop-open'),
                label: 'Ingredient tugadi',
                icon: Icons.no_food_outlined,
                variant: RzButtonVariant.secondary,
                onPressed: () => unawaited(
                  showIngredientStop(context, controller, now: now),
                ),
              ),
            ],
          ),
        ),
      );
    },
  );
}

/// "Ingredient tugadi" oynasi: ingredientlar ro'yxati; "Tugadi" (sabab va muddat bilan) yoki "Qayta ochish".
Future<void> showIngredientStop(
  BuildContext context,
  KdsIngredientsController controller, {
  required DateTime Function() now,
}) => showDialog<void>(
  context: context,
  builder: (BuildContext context) =>
      _IngredientStopDialog(controller: controller, now: now),
);

class _IngredientStopDialog extends StatefulWidget {
  const _IngredientStopDialog({required this.controller, required this.now});

  final KdsIngredientsController controller;
  final DateTime Function() now;

  @override
  State<_IngredientStopDialog> createState() => _IngredientStopDialogState();
}

class _IngredientStopDialogState extends State<_IngredientStopDialog> {
  String? _error;

  Future<void> _stop(KdsIngredient i) async {
    final _StopChoice? choice = await showDialog<_StopChoice>(
      context: context,
      builder: (BuildContext context) =>
          _ReasonDialog(ingredient: i, now: widget.now),
    );
    if (choice == null || !mounted) return;
    await _run(
      () => widget.controller.stopIngredient(
        i,
        reason: choice.reason,
        until: choice.until,
      ),
    );
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() => _error = null);
    try {
      await action();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Dialog(
      insetPadding: const EdgeInsets.all(24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 560, maxHeight: 680),
        child: ListenableBuilder(
          listenable: widget.controller,
          builder: (BuildContext context, _) {
            final List<KdsIngredient> items = widget.controller.ingredients;
            return Column(
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
                  child: items.isEmpty
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
            );
          },
        ),
      ),
    );
  }

  Widget _row(BuildContext context, KdsIngredient i) {
    final RzColors c = context.rz;
    final DateTime? until = i.until;
    final String? reason = i.reason?.trim();
    final String sub = i.stopped
        ? <String>[
            'Tugagan',
            if (reason != null && reason.isNotEmpty) reason,
            if (until != null) '${formatTime(until)} gacha',
          ].join(' · ')
        : '${i.dishes} ta taom yopiladi';
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
                    color: i.stopped ? c.warningSoftFg : c.fgMuted,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          if (i.stopped)
            RzButton(
              label: 'Qayta ochish',
              icon: Icons.lock_open_rounded,
              variant: RzButtonVariant.secondary,
              onPressed: widget.controller.busy
                  ? null
                  : () => unawaited(_run(() => widget.controller.reopen(i))),
            )
          else
            RzButton(
              label: 'Tugadi',
              icon: Icons.block_rounded,
              variant: RzButtonVariant.danger,
              onPressed: widget.controller.busy
                  ? null
                  : () => unawaited(_stop(i)),
            ),
        ],
      ),
    );
  }
}

class _StopChoice {
  const _StopChoice(this.reason, this.until);

  final String? reason;
  final DateTime? until;
}

/// Sabab (ixtiyoriy) va muddat (qo'lda ochilguncha yoki 1/3 soat, bugun oxirigacha).
class _ReasonDialog extends StatefulWidget {
  const _ReasonDialog({required this.ingredient, required this.now});

  final KdsIngredient ingredient;
  final DateTime Function() now;

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
    final DateTime now = widget.now();
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
    title: Text('${widget.ingredient.name} tugadi'),
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
                .pop(_StopChoice(_reason.text.trim(), _untilValue())),
        child: const Text('Tugadi deb belgilash'),
      ),
    ],
  );
}
