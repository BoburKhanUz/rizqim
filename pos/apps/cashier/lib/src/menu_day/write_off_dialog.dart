import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../features/billing/cashier_ui.dart';
import '../shell/approval_dialog.dart';
import '../shell/pos_scope.dart';
import 'menu_day.dart';

/// Ruxsat: partiyani hisobdan chiqarish (`batches.manage`: menejer, oshpaz, ega; kassirda odatda yo'q).
const String batchesManagePermission = 'batches.manage';

/// Bitta ochiq qozon yoki partiya: nomi va qoldig'i bilan.
class BatchChoice {
  const BatchChoice({
    required this.batch,
    required this.name,
    required this.unit,
  });

  final PosBatch batch;
  final String name;
  final PosUnit unit;

  /// "Osh — qoldi 8", "Osh (0.7 porsiya) — qoldi 2.5 kg".
  String get label =>
      '$name · qoldi ${batch.quantityLeft.label(unit, unitLabel: unit == PosUnit.piece ? 'porsiya' : null)}';
}

/// Menyudagi ochiq partiyalar (qoldig'i bor): hisobdan chiqariladigan narsa shu.
List<BatchChoice> openBatchChoices(PosCatalog catalog) {
  final List<BatchChoice> out = <BatchChoice>[];
  for (final PosBatch b in catalog.availability.batches) {
    if (b.isEmpty) continue;
    final PosProduct? product = catalog.product(b.productId);
    final PosVariant? variant = b.variantId == null
        ? product?.variants.firstOrNull
        : product?.variants
              .where((PosVariant v) => v.id == b.variantId)
              .firstOrNull;
    final String variantName = variant == null || variant.name.isEmpty
        ? ''
        : ' (${variant.name})';
    final String label = b.label == null || b.label!.isEmpty
        ? ''
        : ' [${b.label}]';
    out.add(
      BatchChoice(
        batch: b,
        name: '${product?.name ?? 'Taom #${b.productId}'}$variantName$label',
        unit: variant?.unit ?? PosUnit.piece,
      ),
    );
  }
  return out;
}

/// "Hisobdan chiqarish" tugmasi (smena sahifasida): `batch_writeoff` moduli yoqilgan va smena ochiq bo'lsa ko'rinadi
/// (terminaldan qilingan chiqarish smenaning X/Z hisobotiga tushadi).
class BatchWriteOffButton extends StatelessWidget {
  const BatchWriteOffButton({super.key, this.onDone});

  /// Chiqarish yozilgach (hisobotni yangilash uchun).
  final VoidCallback? onDone;

  @override
  Widget build(BuildContext context) {
    if (!menuModulesOf(context).has(PosModule.batchWriteoff)) {
      return const SizedBox.shrink();
    }
    return RzButton(
      key: const ValueKey<String>('batch-writeoff'),
      label: 'Hisobdan chiqarish',
      icon: Icons.delete_sweep_outlined,
      size: RzButtonSize.lg,
      variant: RzButtonVariant.secondary,
      onPressed: () async {
        final PosBatchWriteOff? done = await showBatchWriteOffDialog(context);
        if (done != null) onDone?.call();
      },
    );
  }
}

/// Sotilmay qolgan yoki buzilgan porsiyalarni sabab bilan hisobdan chiqarish (P6-15). Kassirda `batches.manage` yo'q
/// bo'lsa oyna "menejer yoki oshpaz PIN bilan kirsin" deydi (ekranni qulflash tugmasi bilan).
Future<PosBatchWriteOff?> showBatchWriteOffDialog(BuildContext context) =>
    showScopedDialog<PosBatchWriteOff>(
      context,
      (BuildContext _) => const _WriteOffDialog(),
    );

class _WriteOffDialog extends StatefulWidget {
  const _WriteOffDialog();

  @override
  State<_WriteOffDialog> createState() => _WriteOffDialogState();
}

class _WriteOffDialogState extends State<_WriteOffDialog> {
  List<BatchChoice>? _choices;
  BatchChoice? _selected;
  PosWriteOffReason _reason = PosWriteOffReason.unsold;
  bool _closeBatch = false;
  bool _started = false;
  final TextEditingController _quantity = TextEditingController();
  final TextEditingController _note = TextEditingController();
  String? _error;
  bool _busy = false;
  final LostRequest _lost = LostRequest();

  PosScope get _scope => PosScope.of(context);

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
    _quantity.dispose();
    _note.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final PosScope scope = _scope;
    final PosBootstrap? boot = scope.session.bootstrap;
    PosCatalog? catalog =
        scope.data.catalog.cached(PosChannel.hall) ??
        scope.data.catalog.cached(PosChannel.takeaway);
    try {
      if (catalog == null && boot != null) {
        catalog = await scope.data.catalog.load(
          PosChannel.hall,
          menuVersion: boot.menuVersion,
        );
      } else if (boot != null) {
        // The kazan's left-over moves with every sale: take the latest numbers
        await scope.data.catalog.refreshAvailability(
          storeId: boot.store.id,
          branchId: boot.branch.id,
        );
        catalog =
            scope.data.catalog.cached(PosChannel.hall) ??
            scope.data.catalog.cached(PosChannel.takeaway) ??
            catalog;
      }
    } on PosException {
      // The copy the till already has is good enough to choose from
    }
    if (!mounted) return;
    final List<BatchChoice> choices = catalog == null
        ? const <BatchChoice>[]
        : openBatchChoices(catalog);
    setState(() {
      _choices = choices;
      _select(choices.firstOrNull);
    });
  }

  void _select(BatchChoice? choice) {
    _selected = choice;
    _closeBatch = false;
    _quantity.text = choice?.batch.quantityLeft.format() ?? '';
    _error = null;
  }

  PosQuantity? get _parsed {
    final PosQuantity? q = PosQuantity.tryParse(_quantity.text.trim());
    return q != null && q.isPositive ? q : null;
  }

  bool get _isAll {
    final BatchChoice? s = _selected;
    final PosQuantity? q = _parsed;
    return s != null && q != null && q == s.batch.quantityLeft;
  }

  Future<void> _submit() async {
    final BatchChoice? choice = _selected;
    final PosQuantity? q = _parsed;
    final String note = _note.text.trim();
    String? error;
    if (choice == null) {
      error = 'Hisobdan chiqariladigan taomni tanlang.';
    } else if (q == null) {
      error = 'Miqdorni kiriting.';
    } else if (!choice.unit.isMeasured && !q.isWhole) {
      error = 'Porsiya butun son bo\'lishi kerak.';
    } else if (q > choice.batch.quantityLeft) {
      error = "Qoldiqdan ko'p: ${choice.batch.quantityLeft.format()}.";
    } else if (_reason.needsNote && note.isEmpty) {
      error = 'Boshqa sabab uchun izoh yozing.';
    }
    setState(() => _error = error);
    if (error != null) return;
    setState(() => _busy = true);
    final PosScope scope = _scope;
    final bool close = _closeBatch && _isAll;
    try {
      final PosBatchWriteOff done = await _lost.run<PosBatchWriteOff>(
        context,
        '${choice!.batch.id}|${q!.toApiString()}|${_reason.wire}|$note|$close',
        (String? token, String? key) => scope.data.batches.writeOff(
          choice.batch.id,
          quantity: q,
          reason: _reason,
          note: note.isEmpty ? null : note,
          close: close,
          approvalToken: token,
          idempotencyKey: key,
        ),
      );
      if (!mounted) return;
      showPosMessage(
        context,
        'Hisobdan chiqarildi: ${choice.name} — '
        '${q.label(choice.unit, unitLabel: choice.unit == PosUnit.piece ? 'porsiya' : null)} (${_reason.label}).',
      );
      Navigator.pop(context, done);
    } on PosNetworkException {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = "Aloqa yo'q: yozuv holati noma'lum. Qayta yuborish xavfsiz — ikki marta yozilmaydi.";
        });
      }
    } on PosModuleDisabled catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = e.message;
        });
      }
    } on PosForbidden {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = "Hisobdan chiqarish ruxsati yo'q. Menejer yoki oshpaz o'z PIN'i bilan kirsin.";
        });
      }
    } on Object catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      if (e is PosValidationException) {
        setState(() => _error = e.firstError);
      } else {
        showPosError(context, e);
      }
    }
  }

  Future<void> _lockForManager() async {
    final NavigatorState nav = Navigator.of(context);
    final PosSession session = _scope.session;
    nav.pop();
    await session.lock(reason: 'writeoff');
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    if (!_scope.session.can(batchesManagePermission)) {
      return AlertDialog(
        title: const Text('Hisobdan chiqarish'),
        content: SizedBox(
          width: 440,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              const NoticeBanner(
                key: ValueKey<String>('writeoff-needs-manager'),
                message: "Taomni hisobdan chiqarish uchun menejer yoki oshpaz o'z PIN'i bilan kirishi kerak.",
                icon: Icons.lock_outline_rounded,
              ),
              const SizedBox(height: 12),
              Text(
                "Ekranni qulflang: menejer PIN'ini terib kiradi, shu smenada hisobdan chiqaradi va ekranni qaytarib qulflaydi.",
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
            ],
          ),
        ),
        actions: <Widget>[
          RzButton(
            label: 'Yopish',
            variant: RzButtonVariant.ghost,
            onPressed: () => Navigator.pop(context),
          ),
          RzButton(
            key: const ValueKey<String>('writeoff-lock'),
            label: 'Ekranni qulflash',
            icon: Icons.lock_rounded,
            onPressed: _lockForManager,
          ),
        ],
      );
    }
    final List<BatchChoice>? choices = _choices;
    return AlertDialog(
      title: const Text('Hisobdan chiqarish'),
      content: SizedBox(
        width: 480,
        child: choices == null
            ? const SizedBox(height: 96, child: LoadingBox())
            : choices.isEmpty
            ? Text(
                "Hisobdan chiqariladigan ochiq qozon yoki partiya yo'q.",
                key: const ValueKey<String>('writeoff-empty'),
                style: RzType.body.copyWith(color: c.fgMuted),
              )
            : SingleChildScrollView(child: _form(c, choices)),
      ),
      actions: <Widget>[
        RzButton(
          label: 'Bekor qilish',
          variant: RzButtonVariant.ghost,
          onPressed: () => Navigator.pop(context),
        ),
        if (choices != null && choices.isNotEmpty)
          RzButton(
            key: const ValueKey<String>('writeoff-submit'),
            label: 'Hisobdan chiqarish',
            onPressed: _busy ? null : _submit,
          ),
      ],
    );
  }

  Widget _form(RzColors c, List<BatchChoice> choices) {
    final BatchChoice? sel = _selected;
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text('Qozon', style: RzType.footnote.copyWith(color: c.fgMuted)),
        const SizedBox(height: 6),
        ChoiceRow<BatchChoice>(
          options: <BatchChoice, String>{
            for (final BatchChoice b in choices) b: b.label,
          },
          selected: sel,
          onSelected: (BatchChoice b) => setState(() => _select(b)),
        ),
        const SizedBox(height: 16),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(
              child: TextField(
                key: const ValueKey<String>('writeoff-quantity'),
                controller: _quantity,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                inputFormatters: <TextInputFormatter>[
                  FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]')),
                ],
                style: RzType.headline,
                decoration: InputDecoration(
                  labelText: sel != null && sel.unit.isMeasured
                      ? 'Miqdor (${sel.unit.label})'
                      : 'Porsiya soni',
                ),
                onChanged: (_) => setState(() => _error = null),
              ),
            ),
            const SizedBox(width: 12),
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: RzButton(
                key: const ValueKey<String>('writeoff-all'),
                label: 'Hammasi',
                variant: RzButtonVariant.secondary,
                onPressed: sel == null
                    ? null
                    : () => setState(() {
                        _quantity.text = sel.batch.quantityLeft.format();
                        _error = null;
                      }),
              ),
            ),
          ],
        ),
        if (_isAll)
          CheckboxListTile(
            key: const ValueKey<String>('writeoff-close'),
            value: _closeBatch,
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            title: const Text('Qozonni ham yopish'),
            subtitle: const Text("Qolgani yo'q: taom \"tugadi\" bo'ladi."),
            onChanged: (bool? v) => setState(() => _closeBatch = v ?? false),
          ),
        const SizedBox(height: 16),
        Text('Sabab', style: RzType.footnote.copyWith(color: c.fgMuted)),
        const SizedBox(height: 6),
        ChoiceRow<PosWriteOffReason>(
          options: <PosWriteOffReason, String>{
            for (final PosWriteOffReason r in PosWriteOffReason.values)
              r: r.label,
          },
          selected: _reason,
          onSelected: (PosWriteOffReason r) => setState(() {
            _reason = r;
            _error = null;
          }),
        ),
        const SizedBox(height: 12),
        TextField(
          key: const ValueKey<String>('writeoff-note'),
          controller: _note,
          decoration: InputDecoration(
            labelText: _reason.needsNote ? 'Izoh (majburiy)' : 'Izoh',
          ),
          onChanged: (_) => setState(() => _error = null),
        ),
        if (_error != null) ...<Widget>[
          const SizedBox(height: 12),
          Text(
            _error!,
            key: const ValueKey<String>('writeoff-error'),
            style: RzType.body.copyWith(color: c.dangerSoftFg),
          ),
        ],
      ],
    );
  }
}

/// X/Z hisobotdagi "Hisobdan chiqarilgan taomlar" bloki (`batch_writeoffs`): bo'sh bo'lsa hech narsa ko'rsatilmaydi.
class ShiftWriteOffsList extends StatelessWidget {
  const ShiftWriteOffsList({super.key, required this.writeOffs});

  final PosShiftWriteOffs writeOffs;

  @override
  Widget build(BuildContext context) {
    if (writeOffs.items.isEmpty) return const SizedBox.shrink();
    final RzColors c = context.rz;
    return Column(
      key: const ValueKey<String>('shift-writeoffs'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        for (final PosShiftWriteOffRow r in writeOffs.items)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 3),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    '${r.productName} · ${r.reasonText}',
                    style: RzType.body.copyWith(color: c.fg),
                  ),
                ),
                Text(r.quantityText, style: RzType.body.copyWith(color: c.fg)),
              ],
            ),
          ),
      ],
    );
  }
}
