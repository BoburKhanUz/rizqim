import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';
import 'account_form.dart';
import 'repayment_dialog.dart';

/// Nasiya hisoblari (POS_SPEC 2.9, 2.13; 13-ekran 16): qarzdorlar (eng katta qarz birinchi) va jami qarz, telefon yoki
/// ism bo'yicha qidiruv, hisob ko'chirmasi, qarz to'lovi (kredit cheki), hisob ochish va tahrirlash (limit, to'xtatish —
/// `customer_accounts.manage`; kassirda — menejer PIN'i bilan).
class AccountsPage extends StatefulWidget {
  const AccountsPage({super.key});

  @override
  State<AccountsPage> createState() => _AccountsPageState();
}

class _AccountsPageState extends State<AccountsPage> {
  final TextEditingController _query = TextEditingController();
  bool _debtorsOnly = true;
  PosDebtors? _debtors;
  List<PosCustomerAccount>? _accounts;
  PosException? _error;
  bool _loading = false;
  PosCustomerAccount? _selected;
  PosAccountStatement? _statement;
  bool _statementLoading = false;
  int _days = 30;
  bool _started = false;

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
    _query.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final String q = _query.text.trim();
      final PosDebtors debtors = await _scope.data.accounts.debtors();
      final List<PosCustomerAccount> list = q.isEmpty && _debtorsOnly
          ? debtors.accounts
          : (await _scope.data.accounts.list(
              query: q.isEmpty ? null : q,
              debtorsOnly: _debtorsOnly,
              perPage: 100,
            )).accounts;
      if (!mounted) return;
      setState(() {
        _debtors = debtors;
        _accounts = list;
        _error = null;
        if (_selected != null) {
          _selected =
              list
                  .where((PosCustomerAccount a) => a.uuid == _selected!.uuid)
                  .firstOrNull ??
              _selected;
        }
      });
    } on PosException catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _open(PosCustomerAccount a) async {
    setState(() {
      _selected = a;
      _statement = null;
    });
    await _loadStatement();
  }

  Future<void> _loadStatement() async {
    final PosCustomerAccount? a = _selected;
    if (a == null) return;
    setState(() => _statementLoading = true);
    try {
      final DateTime now = _scope.services.now();
      final PosAccountStatement s = await _scope.data.accounts.statement(
        a.uuid,
        from: _days == 0 ? null : isoDay(now.subtract(Duration(days: _days))),
        to: isoDay(now),
      );
      if (!mounted || _selected?.uuid != a.uuid) return;
      setState(() {
        _statement = s;
        _selected = s.account;
      });
    } on PosException catch (e) {
      if (mounted) showPosError(context, e);
    } finally {
      if (mounted) setState(() => _statementLoading = false);
    }
  }

  Future<void> _repay() async {
    final PosAccountWithEntries? r = await showRepaymentDialog(
      context,
      _selected!,
    );
    if (r == null || !mounted) return;
    setState(() => _selected = r.account);
    await Future.wait(<Future<void>>[_load(), _loadStatement()]);
  }

  Future<void> _edit({PosCustomerAccount? account}) async {
    final PosCustomerAccount? saved = await showAccountForm(
      context,
      account: account,
      initialQuery: account == null ? _query.text.trim() : null,
    );
    if (saved == null || !mounted) return;
    await _load();
    if (mounted) await _open(saved);
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    // A cashier opens and changes accounts too, with a manager's PIN when saving
    final bool manage =
        _scope.session.can(PosPermissions.customerAccountsManage) ||
        _scope.session.needsApproval(PosPermissions.customerAccountsManage);
    return Material(
      color: c.page,
      child: LayoutBuilder(
        builder: (BuildContext context, BoxConstraints box) {
          final Widget list = _listPane(c, manage);
          final PosCustomerAccount? sel = _selected;
          if (box.maxWidth < 900) {
            return sel == null
                ? list
                : Column(
                    children: <Widget>[
                      Align(
                        alignment: Alignment.centerLeft,
                        child: TextButton.icon(
                          onPressed: () => setState(() => _selected = null),
                          icon: const Icon(Icons.arrow_back_rounded),
                          label: const Text("Ro'yxat"),
                        ),
                      ),
                      Expanded(child: _detail(c, sel, manage)),
                    ],
                  );
          }
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              SizedBox(
                width: (box.maxWidth * 0.42).clamp(360, 520),
                child: list,
              ),
              VerticalDivider(width: 1, color: c.line),
              Expanded(
                child: sel == null
                    ? const EmptyState(
                        icon: Icons.account_balance_wallet_outlined,
                        title: 'Hisobni tanlang',
                        message: "Chapdan hisobni tanlang: ko'chirma va qarz to'lovi shu yerda.",
                      )
                    : _detail(c, sel, manage),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _listPane(RzColors c, bool manage) {
    final PosDebtors? d = _debtors;
    final List<PosCustomerAccount>? accounts = _accounts;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: PosCard(
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        'Jami qarz',
                        style: RzType.body.copyWith(color: c.fgMuted),
                      ),
                      Text(
                        (d?.totalDebt ?? PosMoney.zero).format(),
                        style: RzType.display.copyWith(
                          color: c.fg,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        '${d?.count ?? 0} qarzdor',
                        style: RzType.footnote.copyWith(color: c.fgMuted),
                      ),
                    ],
                  ),
                ),
                if (manage)
                  RzButton(
                    label: 'Yangi hisob',
                    icon: Icons.person_add_alt_1_rounded,
                    variant: RzButtonVariant.secondary,
                    onPressed: () => _edit(),
                  ),
              ],
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: TextField(
            key: const ValueKey<String>('accounts-search'),
            controller: _query,
            decoration: InputDecoration(
              labelText: 'Telefon yoki ism',
              prefixIcon: const Icon(Icons.search_rounded),
              suffixIcon: _query.text.isEmpty
                  ? null
                  : IconButton(
                      tooltip: 'Tozalash',
                      icon: const Icon(Icons.close_rounded),
                      onPressed: () {
                        _query.clear();
                        unawaited(_load());
                      },
                    ),
            ),
            textInputAction: TextInputAction.search,
            onChanged: (_) => setState(() {}),
            onSubmitted: (_) => unawaited(_load()),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
          child: Row(
            children: <Widget>[
              FilterChip(
                label: const Text('Faqat qarzdorlar'),
                selected: _debtorsOnly,
                materialTapTargetSize: MaterialTapTargetSize.padded,
                onSelected: (bool v) {
                  setState(() => _debtorsOnly = v);
                  unawaited(_load());
                },
              ),
              const Spacer(),
              IconButton(
                tooltip: 'Yangilash',
                icon: const Icon(Icons.refresh_rounded),
                onPressed: _loading ? null : () => unawaited(_load()),
              ),
            ],
          ),
        ),
        Expanded(
          child: _error != null && accounts == null
              ? Padding(
                  padding: const EdgeInsets.all(16),
                  child: InlineError(
                    message: _error!.isNetworkError
                        ? "Aloqa yo'q: hisoblarni yuklab bo'lmadi."
                        : _error!.message,
                    onRetry: () => unawaited(_load()),
                  ),
                )
              : accounts == null
              ? const LoadingBox()
              : accounts.isEmpty
              ? EmptyState(
                  icon: Icons.account_balance_wallet_outlined,
                  title: _query.text.isEmpty
                      ? "Qarzdor yo'q"
                      : 'Hisob topilmadi',
                  message: _query.text.isEmpty
                      ? "Nasiyaga yopilgan hisoblar shu yerda ko'rinadi."
                      : "Boshqa raqam yoki ism bilan qidiring.",
                )
              : ListView.separated(
                  padding: const EdgeInsets.fromLTRB(8, 8, 8, 16),
                  itemCount: accounts.length,
                  separatorBuilder: (_, _) => Divider(height: 1, color: c.line),
                  itemBuilder: (BuildContext context, int i) =>
                      _tile(c, accounts[i]),
                ),
        ),
      ],
    );
  }

  Widget _tile(RzColors c, PosCustomerAccount a) {
    final bool selected = a.uuid == _selected?.uuid;
    final bool overLimit =
        a.creditLimit != null &&
        a.balance >= a.creditLimit! &&
        a.balance.isPositive;
    return ListTile(
      selected: selected,
      selectedTileColor: Color.alphaBlend(c.brandSoft, c.surface),
      minVerticalPadding: 10,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(RzRadius.control),
      ),
      onTap: () => unawaited(_open(a)),
      title: Text(a.name, style: RzType.headline.copyWith(color: c.fg)),
      subtitle: Text(
        <String>[
          ?a.phone,
          ?a.groupName,
          if (a.creditLimit != null) 'limit ${a.creditLimit!.formatAmount()}',
        ].join(' · '),
        style: RzType.footnote.copyWith(color: c.fgMuted),
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: <Widget>[
          Text(
            a.balance.format(),
            style: RzType.headline.copyWith(
              color: a.balance.isPositive ? c.dangerSoftFg : c.fgMuted,
              fontWeight: FontWeight.w700,
            ),
          ),
          if (a.isBlocked)
            const TonePill(
              label: "To'xtatilgan",
              tone: StatusTone.neutral,
              icon: Icons.block_rounded,
            )
          else if (overLimit)
            const TonePill(label: 'Limit tugagan', tone: StatusTone.warning),
        ],
      ),
    );
  }

  Widget _detail(RzColors c, PosCustomerAccount a, bool manage) {
    final PosAccountStatement? s = _statement;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        PosCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(a.name, style: RzType.title.copyWith(color: c.fg)),
                        Text(
                          <String>[?a.phone, ?a.groupName].join(' · '),
                          style: RzType.body.copyWith(color: c.fgMuted),
                        ),
                      ],
                    ),
                  ),
                  if (a.isBlocked)
                    const TonePill(
                      label: "Yangi nasiya to'xtatilgan",
                      tone: StatusTone.neutral,
                      icon: Icons.block_rounded,
                    ),
                ],
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 24,
                runSpacing: 12,
                children: <Widget>[
                  _stat(
                    c,
                    'Qarz',
                    a.balance.format(),
                    a.balance.isPositive ? c.dangerSoftFg : c.fg,
                  ),
                  _stat(c, 'Limit', a.creditLimit?.format() ?? 'Cheksiz', c.fg),
                  _stat(
                    c,
                    'Yana mumkin',
                    a.availableCredit?.format() ?? 'Cheksiz',
                    c.fg,
                  ),
                ],
              ),
              if (a.note != null && a.note!.isNotEmpty) ...<Widget>[
                const SizedBox(height: 12),
                Text(a.note!, style: RzType.body.copyWith(color: c.fgMuted)),
              ],
              const SizedBox(height: 16),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: <Widget>[
                  RzButton(
                    label: "Qarz to'lovi",
                    icon: Icons.payments_rounded,
                    size: RzButtonSize.lg,
                    onPressed: a.balance.isPositive ? _repay : null,
                  ),
                  if (manage)
                    RzButton(
                      label: 'Tahrirlash',
                      icon: Icons.edit_outlined,
                      size: RzButtonSize.lg,
                      variant: RzButtonVariant.secondary,
                      onPressed: () => _edit(account: a),
                    ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        PosCard(
          title: "Ko'chirma",
          trailing: ChoiceRow<int>(
            options: const <int, String>{
              30: '30 kun',
              90: '90 kun',
              0: 'Hammasi',
            },
            selected: _days,
            onSelected: (int d) {
              setState(() => _days = d);
              unawaited(_loadStatement());
            },
          ),
          child: s == null || _statementLoading
              ? const LoadingBox()
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    AmountRow(
                      label: "Boshida",
                      amount: s.openingBalance,
                      muted: true,
                    ),
                    AmountRow(
                      label: 'Nasiyaga yozilgan',
                      amount: s.chargesTotal,
                    ),
                    AmountRow(
                      label: "To'langan",
                      amount: s.repaymentsTotal,
                      negative: true,
                    ),
                    if (!s.adjustmentsTotal.isZero)
                      AmountRow(label: 'Tuzatish', amount: s.adjustmentsTotal),
                    AmountRow(
                      label: 'Oxirida',
                      amount: s.closingBalance,
                      bold: true,
                    ),
                    const Divider(height: 24),
                    if (s.entries.isEmpty)
                      Text(
                        "Bu davrda yozuv yo'q.",
                        style: RzType.body.copyWith(color: c.fgMuted),
                      ),
                    for (final PosAccountEntry e in s.entries.reversed)
                      _entry(c, e),
                  ],
                ),
        ),
      ],
    );
  }

  Widget _stat(RzColors c, String label, String value, Color color) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      Text(label, style: RzType.footnote.copyWith(color: c.fgMuted)),
      Text(
        value,
        style: RzType.title.copyWith(color: color, fontWeight: FontWeight.w700),
      ),
    ],
  );

  Widget _entry(RzColors c, PosAccountEntry e) {
    final bool charge = e.amount.isPositive;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: <Widget>[
          Icon(
            charge ? Icons.north_east_rounded : Icons.south_west_rounded,
            color: charge ? c.dangerSoftFg : c.successSoftFg,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  e.typeLabel ?? e.type,
                  style: RzType.body.copyWith(color: c.fg),
                ),
                Text(
                  <String>[
                    if (e.createdAt != null)
                      '${dm(e.createdAt!, year: true)} ${hm(e.createdAt!)}',
                    if (e.tender != null) tenderLabel(e.tender!),
                    if (e.user?.name != null) e.user!.name!,
                    ?e.note,
                  ].join(' · '),
                  style: RzType.footnote.copyWith(color: c.fgMuted),
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: <Widget>[
              Text(
                '${charge ? '+' : '−'}${e.amount.abs().formatAmount()}',
                style: RzType.body.copyWith(
                  color: charge ? c.dangerSoftFg : c.successSoftFg,
                  fontWeight: FontWeight.w700,
                ),
              ),
              Text(
                'qoldi ${e.balanceAfter.formatAmount()}',
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
