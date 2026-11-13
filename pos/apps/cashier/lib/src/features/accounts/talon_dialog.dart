import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';
import '../billing/payment_messages.dart';
import '../billing/receipts.dart';

/// P6-44: korxona xodimlarining talonlari (chek qog'ozida: korxona, xodim, kod QR va yozuvda, limitlar —
/// `layoutCorporateTalon`). Ro'yxat `customer_accounts.manage` bilan; ruxsat bo'lmasa — talon kodi (yoki telefon)
/// bo'yicha bitta xodimni topib chop etish. Talon ishlatilib tugamaydi (limitlar serverda).
Future<void> showTalonDialog(
  BuildContext context,
  PosCustomerAccount account,
) => showScopedDialog<void>(
  context,
  (BuildContext context) => _TalonDialog(account: account),
);

/// Bitta talonni chop etadi.
Future<bool> printTalon(
  BuildContext context, {
  required String company,
  required String name,
  required String code,
  PosMoney? dailyLimit,
  int? mealsPerDay,
}) {
  final PosScope scope = PosScope.of(context);
  final ReceiptPrinter printer = ReceiptPrinter(scope);
  final CorporateTalon talon = CorporateTalon(
    header: printer.header,
    company: company,
    memberName: name,
    code: code,
    printedAt: scope.services.now().toLocal(),
    dailyLimit: dailyLimit,
    mealsPerDay: mealsPerDay,
  );
  return printer.print(
    context,
    (PaperWidth paper) => layoutCorporateTalon(talon, paper),
  );
}

class _TalonDialog extends StatefulWidget {
  const _TalonDialog({required this.account});

  final PosCustomerAccount account;

  @override
  State<_TalonDialog> createState() => _TalonDialogState();
}

class _TalonDialogState extends State<_TalonDialog> {
  final TextEditingController _code = TextEditingController();
  List<PosAccountMember>? _members;
  String? _listNote;
  String? _error;
  bool _loading = true;
  bool _busy = false;
  bool _started = false;

  String get _company => widget.account.companyName ?? widget.account.name;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    unawaited(_load());
  }

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final List<PosAccountMember> list = await PosScope.of(context)
          .data
          .accounts
          .members(widget.account.uuid);
      if (mounted) setState(() => _members = list);
    } on PosForbidden {
      if (mounted) {
        setState(
          () => _listNote = "Xodimlar ro'yxati menejerga ochiq. Talon kodini kiriting — bitta talon chop etiladi.",
        );
      }
    } on PosException catch (e) {
      if (mounted) setState(() => _listNote = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<bool> _printMember(PosAccountMember m) => printTalon(
    context,
    company: _company,
    name: m.name,
    code: m.code,
    dailyLimit: m.dailyLimit,
    mealsPerDay: m.mealsPerDay,
  );

  Future<void> _printAll() async {
    final List<PosAccountMember> active = <PosAccountMember>[
      for (final PosAccountMember m in _members ?? const <PosAccountMember>[])
        if (m.active) m,
    ];
    setState(() => _busy = true);
    var printed = 0;
    for (final PosAccountMember m in active) {
      if (!mounted) return;
      if (await _printMember(m)) printed++;
    }
    if (!mounted) return;
    setState(() => _busy = false);
    showPosMessage(context, '$printed ta talon chop etildi.');
  }

  Future<void> _printByCode() async {
    final String q = _code.text.trim();
    if (q.isEmpty) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final PosData data = PosScope.of(context).data;
    try {
      final PosCorporateLookup r = await data.accounts.lookupMember(code: q);
      if (!mounted) return;
      if (r.accountUuid != widget.account.uuid) {
        setState(() => _error = 'Bu talon boshqa korxonaniki.');
        return;
      }
      final bool ok = await printTalon(
        context,
        company: r.companyName ?? r.accountName,
        name: r.memberName,
        code: r.code,
        dailyLimit: r.dailyLimit,
        mealsPerDay: r.mealsPerDay,
      );
      if (ok && mounted) {
        showPosMessage(context, 'Talon chop etildi: ${r.memberName}.');
      }
    } on PosException catch (e) {
      if (mounted) setState(() => _error = paymentRefusalText(e) ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final List<PosAccountMember> members =
        _members ?? const <PosAccountMember>[];
    return AlertDialog(
      title: Text('Talonlar · $_company'),
      content: SizedBox(
        width: 520,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                "Talon — xodimning kodi (QR). U ishlatilib tugamaydi: kunlik limit va ovqat soni kassada serverda "
                'tekshiriladi. Yo\'qolsa — panelda yangi kod bering.',
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 12),
              Row(
                children: <Widget>[
                  Expanded(
                    child: TextField(
                      key: const ValueKey<String>('talon-code'),
                      controller: _code,
                      decoration: const InputDecoration(
                        labelText: 'Talon kodi',
                        prefixIcon: Icon(Icons.badge_outlined),
                      ),
                      onSubmitted: (_) => unawaited(_printByCode()),
                    ),
                  ),
                  const SizedBox(width: 8),
                  RzButton(
                    key: const ValueKey<String>('talon-print-code'),
                    label: 'Chop etish',
                    icon: Icons.print_outlined,
                    variant: RzButtonVariant.secondary,
                    onPressed: _busy ? null : () => unawaited(_printByCode()),
                  ),
                ],
              ),
              if (_error != null) ...<Widget>[
                const SizedBox(height: 8),
                Text(
                  _error!,
                  style: RzType.body.copyWith(color: c.dangerSoftFg),
                ),
              ],
              const Divider(height: 24),
              if (_loading)
                const Center(child: CircularProgressIndicator())
              else if (_listNote != null)
                Text(_listNote!, style: RzType.body.copyWith(color: c.fgMuted))
              else if (members.isEmpty)
                Text(
                  "Xodim yo'q: panelda korxona xodimlarini qo'shing.",
                  style: RzType.body.copyWith(color: c.fgMuted),
                )
              else ...<Widget>[
                for (final PosAccountMember m in members)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(m.name),
                    subtitle: Text(
                      <String>[
                        m.code,
                        if (m.dailyLimit != null)
                          'kuniga ${m.dailyLimit!.formatAmount()}',
                        if (m.mealsPerDay != null) '${m.mealsPerDay} ovqat',
                        if (!m.active) "to'xtatilgan",
                      ].join(' · '),
                    ),
                    trailing: IconButton(
                      tooltip: 'Talonni chop etish',
                      icon: const Icon(Icons.print_outlined),
                      onPressed: _busy || !m.active
                          ? null
                          : () => unawaited(_printMember(m)),
                    ),
                  ),
              ],
            ],
          ),
        ),
      ),
      actions: <Widget>[
        RzButton(
          label: 'Yopish',
          variant: RzButtonVariant.ghost,
          onPressed: () => Navigator.pop(context),
        ),
        if (members.any((PosAccountMember m) => m.active))
          RzButton(
            key: const ValueKey<String>('talon-print-all'),
            label: 'Hammasini chop etish',
            icon: Icons.print_rounded,
            loading: _busy,
            onPressed: _busy ? null : () => unawaited(_printAll()),
          ),
      ],
    );
  }
}
