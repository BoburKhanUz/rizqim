import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../features/billing/cashier_ui.dart';
import '../shell/pos_scope.dart';

/// Z oynasida kiritilgan bank terminali jami (P6-16): smenani yopish so'roviga `bank_terminal` bo'lib qo'shiladi.
/// Bo'sh qoldirilsa hech narsa yuborilmaydi — smena baribir yopiladi.
class BankTotalController extends ChangeNotifier {
  PosMoney? _total;
  int? _count;
  String _note = '';
  PosBankTerminalReport? _fromProvider;

  PosMoney? get total => _total;
  int? get count => _count;
  String get note => _note;

  /// Smart-kassada provayderdan olingan hisobot (server hisoblagan farq bilan); qo'lda o'zgartirilsa o'chadi.
  PosBankTerminalReport? get fromProvider => _fromProvider;

  void setTotal(PosMoney? value) {
    _total = value;
    _fromProvider = null;
    notifyListeners();
  }

  void setCount(int? value) {
    _count = value;
    notifyListeners();
  }

  void setNote(String value) {
    _note = value;
    notifyListeners();
  }

  void setProvider(PosBankTerminalReport report) {
    _total = report.bankTotal;
    _count = report.bankCount ?? _count;
    _fromProvider = report;
    notifyListeners();
  }

  /// Smenani yopish so'roviga qo'shiladigan jami (kiritilmagan — `null`).
  PosBankTerminalInput? get input {
    final PosMoney? t = _total;
    if (t == null) return null;
    return PosBankTerminalInput(
      total: t,
      count: _count,
      note: _note.trim().isEmpty ? null : _note.trim(),
    );
  }
}

/// "Bank terminali kunlik jami" (Z oynasida): `terminal_card_flow` moduli yoqilgan va filial `ask_bank_total` desa
/// ko'rinadi; hech narsa bloklanmaydi. Kassir jamni terminal chekidan teradi: Rizqim'dagi karta pul bilan farqni
/// server Z yozilgach hisoblaydi va Z natijasida ko'rsatadi (review P6 S#5 — oldindan taxmin ham, chegara ham
/// ko'rsatilmaydi). Umumiy terminalda kunlik jam kunning oxirgi Z ida kiritiladi. Smart-kassada "Provayderdan olish"
/// tugmasi (sandbox) karta jamini provayderdan oladi.
class BankTotalSection extends StatefulWidget {
  const BankTotalSection({
    super.key,
    required this.flow,
    required this.shiftUuid,
    required this.controller,
  });

  final PosTerminalFlow flow;
  final String shiftUuid;
  final BankTotalController controller;

  @override
  State<BankTotalSection> createState() => _BankTotalSectionState();
}

class _BankTotalSectionState extends State<BankTotalSection> {
  final TextEditingController _total = TextEditingController();
  final TextEditingController _count = TextEditingController();
  final TextEditingController _note = TextEditingController();
  bool _busy = false;
  String? _error;

  BankTotalController get _ctl => widget.controller;

  @override
  void initState() {
    super.initState();
    _ctl.addListener(_sync);
  }

  @override
  void dispose() {
    _ctl.removeListener(_sync);
    _total.dispose();
    _count.dispose();
    _note.dispose();
    super.dispose();
  }

  /// Provayderdan kelgan qiymat maydonlarga yoziladi.
  void _sync() {
    if (!mounted) return;
    if (parseMoney(_total.text) != _ctl.total) {
      _total.text = moneyText(_ctl.total);
    }
    setState(() {});
  }

  Future<void> _fromProvider() async {
    final PosScope scope = PosScope.of(context);
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final PosBankTerminalReport r = await scope.data.shifts.bankReport(
        widget.shiftUuid,
        source: PosBankReportSource.provider,
      );
      if (!mounted) return;
      _ctl.setProvider(r);
      _count.text = r.bankCount == null ? '' : '${r.bankCount}';
    } on PosException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return PosCard(
      key: const ValueKey<String>('bank-total-section'),
      title: 'Bank terminali: kunlik jami',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(
            "Bank terminalining kun oxiridagi (settlement) jami summasini chekidan kiriting. Bo'sh qoldirsangiz — smena baribir yopiladi. Farqni server Z dan keyin ko'rsatadi.",
            style: RzType.body.copyWith(color: c.fgMuted),
          ),
          if (widget.flow.sharedTerminal) ...<Widget>[
            const SizedBox(height: 8),
            const NoticeBanner(
              key: ValueKey<String>('bank-shared'),
              message: "Umumiy bank terminali: kunlik jamni kunning oxirgi smenasini yopayotganda kiriting — u kunning barcha smenalari bilan solishtiriladi.",
              tone: StatusTone.info,
            ),
          ],
          const SizedBox(height: 12),
          MoneyField(
            fieldKey: const ValueKey<String>('bank-total'),
            controller: _total,
            label: 'Terminal jami',
            onChanged: _ctl.setTotal,
          ),
          const SizedBox(height: 12),
          TextField(
            key: const ValueKey<String>('bank-count'),
            controller: _count,
            keyboardType: TextInputType.number,
            inputFormatters: <TextInputFormatter>[
              FilteringTextInputFormatter.digitsOnly,
            ],
            decoration: const InputDecoration(
              labelText: "To'lovlar soni (ixtiyoriy)",
            ),
            onChanged: (String t) => _ctl.setCount(int.tryParse(t)),
          ),
          const SizedBox(height: 12),
          TextField(
            key: const ValueKey<String>('bank-note'),
            controller: _note,
            decoration: const InputDecoration(labelText: 'Izoh (ixtiyoriy)'),
            onChanged: _ctl.setNote,
          ),
          // Smart kassa: the provider's own card total (sandbox driver for now), only behind the flag
          if (widget.flow.providerTotals) ...<Widget>[
            const SizedBox(height: 12),
            Align(
              alignment: Alignment.centerLeft,
              child: RzButton(
                key: const ValueKey<String>('bank-provider'),
                label: 'Provayderdan olish',
                icon: Icons.cloud_download_outlined,
                variant: RzButtonVariant.secondary,
                onPressed: _busy ? null : () => unawaited(_fromProvider()),
              ),
            ),
          ],
          if (_error != null) ...<Widget>[
            const SizedBox(height: 8),
            Text(_error!, style: RzType.body.copyWith(color: c.dangerSoftFg)),
          ],
          if (!widget.flow.checklistComplete) ...<Widget>[
            const SizedBox(height: 8),
            Text(
              "Bank terminalini o'rnatish ro'yxati to'liq emas: ega panelda to'ldirsin.",
              key: const ValueKey<String>('bank-checklist'),
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
          ],
        ],
      ),
    );
  }
}

/// Z oynasida bank terminali jamini so'rash kerakmi: `terminal_card_flow` moduli yoqilgan va filial `ask_bank_total`
/// desa — oqim, bo'lmasa `null` (bo'lim ko'rsatilmaydi).
PosTerminalFlow? bankTotalFlowOf(BuildContext context) {
  final PosBootstrap? boot = PosScope.maybeOf(context)?.session.bootstrap;
  if (boot == null || !boot.modules.has(PosModule.terminalCardFlow)) {
    return null;
  }
  final PosTerminalFlow? flow = boot.terminalFlow;
  return flow != null && flow.askBankTotal ? flow : null;
}
