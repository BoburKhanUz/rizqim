import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';

/// Nasiya hisobini ochish yoki o'zgartirish (`customer_accounts.manage`: ega, menejer; kassir — menejer PIN'i bilan,
/// saqlashda tasdiq oynasi ochiladi): ism, telefon, guruh, limit (bo'sh — cheksiz, 0 — nasiya yo'q), izoh, "yangi nasiya
/// to'xtatilgan".
Future<PosCustomerAccount?> showAccountForm(
  BuildContext context, {
  PosCustomerAccount? account,
  String? initialQuery,
}) => showScopedDialog<PosCustomerAccount>(
  context,
  (BuildContext _) => AccountForm(account: account, initialQuery: initialQuery),
);

class AccountForm extends StatefulWidget {
  const AccountForm({super.key, this.account, this.initialQuery});

  final PosCustomerAccount? account;

  /// Qidiruvdagi matn: raqam bo'lsa telefonga, aks holda ismga yoziladi.
  final String? initialQuery;

  @override
  State<AccountForm> createState() => _AccountFormState();
}

class _AccountFormState extends State<AccountForm> {
  late final PosCustomerAccount? a = widget.account;
  late final TextEditingController _name = TextEditingController(
    text: a?.name ?? (_queryIsPhone ? '' : (widget.initialQuery ?? '')),
  );
  late final TextEditingController _phone = TextEditingController(
    text: a?.phone ?? (_queryIsPhone ? widget.initialQuery : ''),
  );
  late final TextEditingController _group = TextEditingController(
    text: a?.groupName ?? '',
  );
  late final TextEditingController _limit = TextEditingController(
    text: moneyText(a?.creditLimit),
  );
  late final TextEditingController _note = TextEditingController(
    text: a?.note ?? '',
  );
  late bool _unlimited = a != null && a!.creditLimit == null;
  late bool _blocked = a?.isBlocked ?? false;
  bool _busy = false;
  String? _error;

  /// Javobsiz qolgan saqlash: aynan o'zi (kalit, tasdiq) qayta yuboriladi, hisob ikki marta ochilmaydi.
  final LostRequest _lost = LostRequest();

  bool get _queryIsPhone =>
      RegExp(r'^[\d +()-]{4,}$').hasMatch(widget.initialQuery ?? '');

  @override
  void dispose() {
    for (final TextEditingController t in <TextEditingController>[
      _name,
      _phone,
      _group,
      _limit,
      _note,
    ]) {
      t.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    final String name = _name.text.trim();
    if (name.isEmpty) {
      setState(() => _error = 'Ismini yozing.');
      return;
    }
    final PosMoney? limit = parseMoney(_limit.text);
    final PosAccountInput input = PosAccountInput(
      name: name,
      phone: _phone.text.trim().isEmpty ? null : _phone.text.trim(),
      groupName: _group.text.trim().isEmpty ? null : _group.text.trim(),
      creditLimit: _unlimited ? null : (limit ?? PosMoney.zero),
      clearCreditLimit: _unlimited,
      note: _note.text.trim().isEmpty ? null : _note.text.trim(),
      isBlocked: a == null ? null : _blocked,
    );
    setState(() {
      _busy = true;
      _error = null;
    });
    final PosScope scope = PosScope.of(context);
    try {
      final PosCustomerAccount saved = await _lost.run<PosCustomerAccount>(
        context,
        '${a?.uuid}|${jsonEncode(input.toJson())}',
        (String? token, String? key) => a == null
            ? scope.data.accounts.create(
                input,
                approvalToken: token,
                idempotencyKey: key,
              )
            : scope.data.accounts.update(
                a!.uuid,
                input,
                approvalToken: token,
                idempotencyKey: key,
              ),
      );
      if (mounted) Navigator.pop(context, saved);
    } on PosApprovalCancelled {
      // The manager did not confirm: the form stays open
    } on PosNetworkException {
      if (mounted) {
        setState(
          () => _error = "Aloqa yo'q: saqlandimi, noma'lum. Qayta saqlash xavfsiz — ikki marta yozilmaydi.",
        );
      }
    } on PosValidationException catch (e) {
      if (mounted) setState(() => _error = e.firstError);
    } on Object catch (e) {
      if (mounted) showPosError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return AlertDialog(
      title: Text(a == null ? 'Yangi nasiya hisobi' : 'Hisobni tahrirlash'),
      content: SizedBox(
        width: 460,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              TextField(
                key: const ValueKey<String>('account-name'),
                controller: _name,
                decoration: const InputDecoration(
                  labelText: 'Ism yoki guruh egasi',
                ),
                textCapitalization: TextCapitalization.words,
              ),
              const SizedBox(height: 12),
              TextField(
                key: const ValueKey<String>('account-phone'),
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Telefon',
                  hintText: '+998 90 123 45 67',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _group,
                decoration: const InputDecoration(
                  labelText: 'Guruh (ixtiyoriy)',
                  hintText: 'Gap guruhi',
                ),
              ),
              const SizedBox(height: 12),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _unlimited,
                title: const Text('Limitsiz'),
                onChanged: (bool v) => setState(() => _unlimited = v),
              ),
              if (!_unlimited)
                MoneyField(
                  fieldKey: const ValueKey<String>('account-limit'),
                  controller: _limit,
                  label: 'Nasiya limiti',
                  helperText: "0 — nasiya berilmaydi",
                ),
              const SizedBox(height: 12),
              TextField(
                controller: _note,
                decoration: const InputDecoration(labelText: 'Izoh'),
              ),
              if (a != null)
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _blocked,
                  title: const Text("Yangi nasiya to'xtatilgan"),
                  subtitle: const Text("Qarz to'lovi qabul qilinaveradi"),
                  onChanged: (bool v) => setState(() => _blocked = v),
                ),
              if (_error != null) ...<Widget>[
                const SizedBox(height: 8),
                Text(
                  _error!,
                  style: RzType.body.copyWith(color: c.dangerSoftFg),
                ),
              ],
            ],
          ),
        ),
      ),
      actions: <Widget>[
        RzButton(
          label: 'Bekor qilish',
          variant: RzButtonVariant.ghost,
          onPressed: () => Navigator.pop(context),
        ),
        RzButton(label: 'Saqlash', loading: _busy, onPressed: _save),
      ],
    );
  }
}
