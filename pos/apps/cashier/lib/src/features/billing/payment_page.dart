import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/destinations.dart';
import '../../shell/pos_scope.dart';
import '../accounts/account_form.dart';
import 'billing_page.dart';
import 'cashier_ui.dart';
import 'receipts.dart';
import 'tender_form.dart';

/// To'lov natijasi ([PaymentPage] dan qaytadi).
class PaymentOutcome {
  const PaymentOutcome({required this.bill, required this.orderClosed});

  final PosBill bill;
  final bool orderClosed;
}

/// Qo'shilgan to'lov qatori (aralash to'lov uchun).
class _Part {
  _Part(this.input, this.label);

  final PosTenderInput input;
  final String label;
}

/// Bitta schyotni to'lash (POS_SPEC 2.9): naqd (berilgan pul, qaytim, tez summalar), karta (turi va tranzaksiya
/// raqami majburiy), QR, bron avansidan, nasiyaga (hisob tanlash yoki ochish, limit, menejer tasdig'i); bir nechta tur
/// birga (aralash). To'langach sotuv cheki chop etiladi, naqdda kassa qutisi ochiladi; chek kutilsa — "fiskal
/// kutmoqda" va qayta urinish.
class PaymentPage extends StatefulWidget {
  const PaymentPage({
    super.key,
    required this.bill,
    this.order,
    this.booking,
    this.depositAvailable = PosMoney.zero,
    this.billsCount = 1,
  });

  final PosBill bill;
  final PosOrder? order;

  /// Buyurtma ochilgan bron (ismi va vaqti ko'rsatiladi; bo'lmasa ham avans ishlaydi).
  final PosBooking? booking;

  /// Bron avanslaridan qolgan summa (`GET /pos/orders/{uuid}/bills` `order.deposit_available`, P3-26).
  final PosMoney depositAvailable;
  final int billsCount;

  @override
  State<PaymentPage> createState() => _PaymentPageState();
}

class _PaymentPageState extends State<PaymentPage> {
  late PosBill _bill = widget.bill;
  final List<_Part> _parts = <_Part>[];
  PosTender _tab = PosTender.cash;
  late TenderDraft _draft = TenderDraft(amount: _remaining);
  String? _formError;
  bool _busy = false;

  /// Javob kelmagan to'lov: aynan shu to'lovlar bilan qayta yuborishda o'sha `Idempotency-Key`.
  String? _retryKey;
  String? _retrySignature;

  /// To'landi, chek kutilmoqda.
  PosBillResult? _pending;
  PosException? _blocking;

  // Avans
  late final TextEditingController _prepay = TextEditingController();

  // Nasiya
  final TextEditingController _accountQuery = TextEditingController();
  final TextEditingController _accountAmount = TextEditingController();
  List<PosCustomerAccount>? _accounts;
  PosCustomerAccount? _account;
  bool _searching = false;

  PosScope get _scope => PosScope.of(context);

  PosMoney get _added => PosMoney.sum(_parts.map((_Part p) => p.input.amount));

  PosMoney get _remaining {
    final PosMoney r = _bill.dueAmount - _added;
    return r.isNegative ? PosMoney.zero : r;
  }

  PosMoney get _depositAvailable => widget.depositAvailable;

  bool get _prepaymentUsed =>
      _parts.any((_Part p) => p.input.tender == PosTender.prepayment);

  bool get _accountUsed =>
      _parts.any((_Part p) => p.input.tender == PosTender.account);

  @override
  void initState() {
    super.initState();
    if (_depositAvailable.isPositive) {
      // Avans bo'lsa birinchi taklif qilinadi (POS_SPEC 2.9)
      _tab = PosTender.prepayment;
      _prepay.text = moneyText(_min(_depositAvailable, _remaining));
    }
  }

  @override
  void dispose() {
    _prepay.dispose();
    _accountQuery.dispose();
    _accountAmount.dispose();
    super.dispose();
  }

  static PosMoney _min(PosMoney a, PosMoney b) => a < b ? a : b;

  void _selectTab(PosTender t) {
    setState(() {
      _tab = t;
      _formError = null;
      switch (t) {
        case PosTender.cash || PosTender.card || PosTender.qr:
          _draft = TenderDraft(tender: t, amount: _remaining);
        case PosTender.prepayment:
          _prepay.text = moneyText(_min(_depositAvailable, _remaining));
        case PosTender.account:
          _accountAmount.text = moneyText(_accountDefault());
          if (_accounts == null) unawaited(_searchAccounts());
      }
    });
  }

  PosMoney _accountDefault() {
    final PosMoney? available = _account?.availableCredit;
    return available == null ? _remaining : _min(available, _remaining);
  }

  /// Joriy formadagi to'lov (xato bo'lsa — `null` va [_formError]).
  _Part? _currentPart() {
    final PosMoney remaining = _remaining;
    switch (_tab) {
      case PosTender.cash || PosTender.card || PosTender.qr:
        final String? error = _draft.validate(max: remaining);
        if (error != null) {
          _formError = error;
          return null;
        }
        final String label = switch (_tab) {
          PosTender.card =>
            'Karta (${cardTypes[_draft.cardType] ?? _draft.cardType}) · ${_draft.transactionId.trim()}',
          PosTender.cash when _draft.tendered != null =>
            'Naqd · berildi ${_draft.tendered!.formatAmount()}, qaytim ${_draft.change.formatAmount()}',
          _ => _tab.label,
        };
        return _Part(_draft.toInput(), label);
      case PosTender.prepayment:
        final PosMoney? amount = parseMoney(_prepay.text);
        if (amount == null || !amount.isPositive) {
          _formError = 'Summani kiriting.';
          return null;
        }
        if (amount > _depositAvailable) {
          _formError =
              "Avans yetarli emas: mavjud ${_depositAvailable.format()}.";
          return null;
        }
        if (amount > remaining) {
          _formError = "Summa qoldiqdan ko'p: ${remaining.format()}.";
          return null;
        }
        return _Part(PosTenderInput.prepayment(amount), 'Avans hisobidan');
      case PosTender.account:
        final PosCustomerAccount? a = _account;
        if (a == null) {
          _formError = 'Nasiya hisobini tanlang.';
          return null;
        }
        if (a.isBlocked) {
          _formError = "Bu hisobga yangi nasiya to'xtatilgan.";
          return null;
        }
        final PosMoney? amount = parseMoney(_accountAmount.text);
        if (amount == null || !amount.isPositive) {
          _formError = 'Summani kiriting.';
          return null;
        }
        if (a.availableCredit != null && amount > a.availableCredit!) {
          _formError =
              'Limit yetmaydi: yana ${a.availableCredit!.format()} nasiya mumkin.';
          return null;
        }
        if (amount > remaining) {
          _formError = "Summa qoldiqdan ko'p: ${remaining.format()}.";
          return null;
        }
        return _Part(
          PosTenderInput.account(amount, accountUuid: a.uuid),
          'Nasiyaga · ${a.name}',
        );
    }
  }

  bool get _formHasAmount => switch (_tab) {
    PosTender.prepayment => parseMoney(_prepay.text)?.isPositive ?? false,
    PosTender.account =>
      _account != null &&
          (parseMoney(_accountAmount.text)?.isPositive ?? false),
    _ => _draft.amount?.isPositive ?? false,
  };

  /// Joriy formadagi summa (qoldiqdan oshmaydi).
  PosMoney get _formAmount {
    final PosMoney? m = switch (_tab) {
      PosTender.prepayment => parseMoney(_prepay.text),
      PosTender.account => parseMoney(_accountAmount.text),
      _ => _draft.amount,
    };
    if (m == null) return PosMoney.zero;
    return m > _remaining ? _remaining : m;
  }

  void _addPart() {
    setState(() {
      _formError = null;
      final _Part? part = _currentPart();
      if (part == null) return;
      if (part.input.tender == PosTender.prepayment && _prepaymentUsed ||
          part.input.tender == PosTender.account && _accountUsed) {
        _formError = "Bu turdagi to'lov schyotga bir marta qo'shiladi.";
        return;
      }
      _parts.add(part);
      _resetForms();
    });
  }

  void _resetForms() {
    _draft = TenderDraft(
      tender: _tab == PosTender.prepayment || _tab == PosTender.account
          ? PosTender.cash
          : _tab,
      amount: _remaining,
    );
    if (_tab == PosTender.prepayment || _tab == PosTender.account) {
      _tab = PosTender.cash;
    }
  }

  /// To'lanadigan qismlar: qo'shilganlar, ular bo'lmasa — joriy forma.
  List<_Part>? _toSubmit() {
    if (_parts.isNotEmpty) {
      // Formada summa bo'lsa — u ham qo'shiladi; bo'sh bo'lsa schyot qisman to'lanadi
      if (!_remaining.isPositive || !_formHasAmount) {
        return List<_Part>.of(_parts);
      }
      final _Part? extra = _currentPart();
      return extra == null ? null : <_Part>[..._parts, extra];
    }
    final _Part? part = _currentPart();
    return part == null ? null : <_Part>[part];
  }

  Future<void> _submit() async {
    setState(() => _formError = null);
    final List<_Part>? parts = _toSubmit();
    if (parts == null) {
      setState(() {});
      return;
    }
    final List<PosTenderInput> tenders = <PosTenderInput>[
      for (final _Part p in parts) p.input,
    ];
    final String signature = tenders
        .map((PosTenderInput t) => t.toJson().toString())
        .join('|');
    final String? key = _retrySignature == signature ? _retryKey : null;
    setState(() {
      _busy = true;
      _blocking = null;
    });
    try {
      final PosBillResult r = await withApproval<PosBillResult>(
        context,
        (String? token) => _scope.data.billing.pay(
          _bill.uuid,
          tenders: tenders,
          shiftUuid: _scope.session.shift?.uuid,
          approvalToken: token,
          idempotencyKey: token == null ? key : null,
        ),
      );
      _retryKey = null;
      _retrySignature = null;
      if (!mounted) return;
      _bill = r.bill;
      _parts.clear();
      final bool cash = tenders.any(
        (PosTenderInput t) => t.tender == PosTender.cash,
      );
      await _afterPayment(r, openDrawer: cash);
    } on PosNetworkException catch (e) {
      _retryKey = e.idempotencyKey;
      _retrySignature = signature;
      if (mounted) {
        showPosMessage(
          context,
          "Aloqa yo'q: to'lov holati noma'lum. Xuddi shu to'lovni qayta yuborish xavfsiz — ikki marta yozilmaydi.",
        );
      }
    } on PosConflict catch (e) {
      if (e.reason == 'shift_not_open') {
        if (mounted) setState(() => _blocking = e);
      } else if (mounted) {
        showPosError(context, e);
      }
    } on Object catch (e) {
      if (mounted) showPosError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _afterPayment(
    PosBillResult r, {
    required bool openDrawer,
  }) async {
    final ReceiptPrinter printer = ReceiptPrinter.of(context);
    if (r.bill.fiscal.status == 'issued') {
      await printSale(context, r.bill, widget.order, openDrawer: openDrawer);
      if (!mounted) return;
      Navigator.pop(
        context,
        PaymentOutcome(bill: r.bill, orderClosed: r.order.isClosed),
      );
      return;
    }
    if (r.bill.dueAmount.isPositive) {
      // Qisman to'landi: qolgani keyingi to'lovda
      if (openDrawer) printer.openDrawer();
      setState(() {
        _draft = TenderDraft(amount: _remaining);
        _tab = PosTender.cash;
      });
      showPosMessage(
        context,
        "Qisman to'landi. Qoldi: ${r.bill.dueAmount.format()}",
      );
      return;
    }
    // Pul olindi, chek kutilmoqda
    if (openDrawer) printer.openDrawer();
    setState(() => _pending = r);
  }

  Future<void> _retryFiscal() async {
    setState(() => _busy = true);
    await runGuarded(context, () async {
      final PosBillResult r = await _scope.data.billing.fiscalRetry(_bill.uuid);
      if (!mounted) return;
      _bill = r.bill;
      if (r.bill.fiscal.status == 'issued') {
        await printSale(context, r.bill, widget.order);
        if (!mounted) return;
        Navigator.pop(
          context,
          PaymentOutcome(bill: r.bill, orderClosed: r.order.isClosed),
        );
      } else {
        setState(() => _pending = r);
        showPosMessage(
          context,
          "Chek hali kutilmoqda. Birozdan keyin qayta urinib ko'ring yoki Navbat bo'limidan kuzating.",
        );
      }
    });
    if (mounted) setState(() => _busy = false);
  }

  Future<void> _searchAccounts() async {
    setState(() => _searching = true);
    try {
      final String q = _accountQuery.text.trim();
      final List<PosCustomerAccount> list = q.isEmpty
          ? (await _scope.data.accounts.debtors()).accounts
          : (await _scope.data.accounts.list(query: q)).accounts;
      if (mounted) setState(() => _accounts = list);
    } on PosException catch (e) {
      if (mounted) showPosError(context, e);
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  Future<void> _createAccount() async {
    final PosCustomerAccount? created = await showAccountForm(
      context,
      initialQuery: _accountQuery.text.trim(),
    );
    if (created == null || !mounted) return;
    setState(() {
      _account = created;
      _accounts = <PosCustomerAccount>[created, ...?_accounts];
      _accountAmount.text = moneyText(_accountDefault());
    });
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Scaffold(
      backgroundColor: c.page,
      appBar: AppBar(
        title: Text(
          widget.billsCount > 1 ? "To'lov · ${_bill.number}-schyot" : "To'lov",
        ),
      ),
      body: _pending != null ? _fiscalWaiting(c) : _form(c),
    );
  }

  Widget _fiscalWaiting(RzColors c) => Center(
    child: ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 520),
      child: PosCard(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Icon(Icons.hourglass_top_rounded, size: 56, color: c.warning),
            const SizedBox(height: 12),
            Text(
              'Fiskal kutmoqda',
              textAlign: TextAlign.center,
              style: RzType.title.copyWith(color: c.fg),
            ),
            const SizedBox(height: 8),
            Text(
              "Pul olindi (${_bill.paidAmount.format()}), lekin sotuv cheki hali chiqmadi: provayder javob bermadi. "
              "Chek chiqmaguncha buyurtma yopilmaydi. Server chekni o'zi qayta so'raydi.",
              textAlign: TextAlign.center,
              style: RzType.body.copyWith(color: c.fgMuted),
            ),
            if (_bill.fiscal.error != null) ...<Widget>[
              const SizedBox(height: 8),
              Text(
                _bill.fiscal.error!,
                textAlign: TextAlign.center,
                style: RzType.footnote.copyWith(color: c.dangerSoftFg),
              ),
            ],
            const SizedBox(height: 16),
            RzButton(
              label: 'Qayta urinish',
              icon: Icons.refresh_rounded,
              size: RzButtonSize.lg,
              onPressed: _busy ? null : _retryFiscal,
            ),
            const SizedBox(height: 8),
            RzButton(
              label: 'Keyinroq (Navbatda kuzatish)',
              variant: RzButtonVariant.ghost,
              onPressed: () => Navigator.pop(
                context,
                PaymentOutcome(bill: _bill, orderClosed: false),
              ),
            ),
          ],
        ),
      ),
    ),
  );

  Widget _form(RzColors c) {
    final Widget summary = _summary(c);
    final Widget entry = _entry(c);
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints box) {
        if (box.maxWidth < 900) {
          return ListView(
            padding: const EdgeInsets.all(16),
            children: <Widget>[summary, const SizedBox(height: 16), entry],
          );
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(
              flex: 4,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: <Widget>[summary],
              ),
            ),
            Expanded(
              flex: 6,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(0, 16, 16, 16),
                children: <Widget>[entry],
              ),
            ),
          ],
        );
      },
    );
  }

  Widget _summary(RzColors c) {
    final PosMoney remaining = _remaining;
    final bool canSubmit =
        !_busy && (_parts.isNotEmpty || remaining.isPositive);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        PosCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text("To'lanadi", style: RzType.body.copyWith(color: c.fgMuted)),
              Text(
                _bill.dueAmount.format(),
                key: const ValueKey<String>('payment-due'),
                style: RzType.display.copyWith(
                  color: c.fg,
                  fontWeight: FontWeight.w800,
                ),
              ),
              if (_bill.paidAmount.isPositive)
                AmountRow(
                  label: "Oldin to'langan",
                  amount: _bill.paidAmount,
                  muted: true,
                ),
              AmountRow(label: 'Schyot jami', amount: _bill.total, muted: true),
              if (_parts.isNotEmpty) ...<Widget>[
                const Divider(height: 24),
                for (var i = 0; i < _parts.length; i++)
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          _parts[i].label,
                          style: RzType.body.copyWith(color: c.fg),
                        ),
                      ),
                      Text(
                        _parts[i].input.amount.format(),
                        style: RzType.body.copyWith(color: c.fg),
                      ),
                      IconButton(
                        tooltip: "Olib tashlash",
                        icon: const Icon(Icons.close_rounded),
                        onPressed: () => setState(() => _parts.removeAt(i)),
                      ),
                    ],
                  ),
                AmountRow(
                  label: 'Qoldi',
                  amount: remaining,
                  bold: true,
                  tone: remaining.isZero
                      ? StatusTone.success
                      : StatusTone.warning,
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 12),
        if (_blocking != null) ...<Widget>[
          NoticeBanner(
            message: _blocking!.message,
            tone: StatusTone.danger,
            action: RzButton(
              label: 'Smenani ochish',
              variant: RzButtonVariant.secondary,
              onPressed: () {
                final PosScope scope = _scope;
                Navigator.of(context).popUntil((Route<Object?> r) => r.isFirst);
                scope.go(CashierDestination.shift);
              },
            ),
          ),
          const SizedBox(height: 12),
        ],
        RzButton(
          key: const ValueKey<String>('payment-submit'),
          label: _parts.isEmpty
              ? "To'lash"
              : "To'lash: ${(_added + (_formHasAmount && remaining.isPositive ? _formAmount : PosMoney.zero)).format()}",
          icon: Icons.check_rounded,
          size: RzButtonSize.lg,
          fullWidth: true,
          onPressed: canSubmit ? _submit : null,
        ),
        if (_parts.isNotEmpty && remaining.isPositive && !_formHasAmount)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              "Qoldiq keyingi to'lovda olinadi (schyot qisman to'lanadi).",
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
          ),
      ],
    );
  }

  Widget _entry(RzColors c) {
    final bool mixedReady = _remaining.isPositive;
    return PosCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          ChoiceRow<PosTender>(
            options: <PosTender, String>{
              PosTender.cash: 'Naqd',
              PosTender.card: 'Karta',
              PosTender.qr: 'QR',
              if (_depositAvailable.isPositive) PosTender.prepayment: 'Avans',
              PosTender.account: 'Nasiya',
            },
            selected: _tab,
            onSelected: _selectTab,
          ),
          const SizedBox(height: 16),
          switch (_tab) {
            PosTender.cash || PosTender.card || PosTender.qr => TenderForm(
              key: ValueKey<Object>('form-$_tab-${_parts.length}'),
              draft: _draft,
              showTenderChoice: false,
              tenders: <PosTender>[_tab],
              error: _formError,
              onChanged: () => setState(() => _formError = null),
            ),
            PosTender.prepayment => _prepaymentForm(c),
            PosTender.account => _accountForm(c),
          },
          const SizedBox(height: 16),
          if (mixedReady)
            RzButton(
              key: const ValueKey<String>('payment-add'),
              label: "Qo'shish (aralash to'lov)",
              icon: Icons.add_rounded,
              variant: RzButtonVariant.secondary,
              onPressed: _busy ? null : _addPart,
            ),
        ],
      ),
    );
  }

  Widget _prepaymentForm(RzColors c) {
    final PosBooking? b = widget.booking;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (!_depositAvailable.isPositive)
          const NoticeBanner(
            message: "Bu buyurtmaning bron avansi yo'q.",
            tone: StatusTone.neutral,
          )
        else ...<Widget>[
          AmountRow(
            label: 'Bron avansi: mavjud',
            amount: _depositAvailable,
            bold: true,
            tone: StatusTone.success,
          ),
          Text(
            '${b == null ? 'Bron' : '${b.contactName ?? 'Bron'} · ${dm(b.startsAt)} ${hm(b.startsAt)}'}. '
            "Sotuv cheki to'liq summaga chiqadi, avans undagi oldindan to'langan qism bo'ladi.",
            style: RzType.footnote.copyWith(color: c.fgMuted),
          ),
          const SizedBox(height: 12),
          MoneyField(
            fieldKey: const ValueKey<String>('prepay-amount'),
            controller: _prepay,
            label: 'Avansdan ayiriladi',
            onChanged: (_) => setState(() => _formError = null),
          ),
        ],
        if (_formError != null) ...<Widget>[
          const SizedBox(height: 12),
          Text(_formError!, style: RzType.body.copyWith(color: c.dangerSoftFg)),
        ],
      ],
    );
  }

  Widget _accountForm(RzColors c) {
    final PosSession session = _scope.session;
    final PosCustomerAccount? selected = _account;
    final bool canCreate = session.can(PosPermissions.customerAccountsManage);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (!session.can(PosPermissions.customerAccountsManage))
          const Padding(
            padding: EdgeInsets.only(bottom: 12),
            child: NoticeBanner(
              message: "Nasiyaga yopish menejer tasdig'i bilan: to'lashda menejer PIN teradi.",
              tone: StatusTone.info,
              icon: Icons.verified_user_outlined,
            ),
          ),
        Row(
          children: <Widget>[
            Expanded(
              child: TextField(
                key: const ValueKey<String>('account-search'),
                controller: _accountQuery,
                decoration: const InputDecoration(
                  labelText: 'Telefon yoki ism',
                  prefixIcon: Icon(Icons.search_rounded),
                ),
                onSubmitted: (_) => unawaited(_searchAccounts()),
              ),
            ),
            const SizedBox(width: 8),
            RzButton(
              label: 'Qidirish',
              variant: RzButtonVariant.secondary,
              loading: _searching,
              onPressed: () => unawaited(_searchAccounts()),
            ),
          ],
        ),
        const SizedBox(height: 8),
        if (_accounts != null && _accounts!.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Text(
              'Hisob topilmadi.',
              style: RzType.body.copyWith(color: c.fgMuted),
            ),
          ),
        for (final PosCustomerAccount a
            in (_accounts ?? const <PosCustomerAccount>[]).take(6))
          ListTile(
            contentPadding: EdgeInsets.zero,
            selected: selected?.uuid == a.uuid,
            leading: Icon(
              selected?.uuid == a.uuid
                  ? Icons.radio_button_checked_rounded
                  : Icons.radio_button_off_rounded,
            ),
            onTap: () => setState(() {
              _account = a;
              _formError = null;
              _accountAmount.text = moneyText(_accountDefault());
            }),
            title: Text(a.name),
            subtitle: Text(
              <String>[
                ?a.phone,
                'qarz ${a.balance.formatAmount()}',
                a.creditLimit == null
                    ? 'limit yo\'q'
                    : 'yana ${a.availableCredit?.formatAmount() ?? '0'} mumkin',
                if (a.isBlocked) "nasiya to'xtatilgan",
              ].join(' · '),
            ),
          ),
        if (canCreate)
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: _createAccount,
              icon: const Icon(Icons.person_add_alt_1_rounded),
              label: const Text('Yangi hisob ochish'),
            ),
          ),
        if (selected != null) ...<Widget>[
          const SizedBox(height: 8),
          MoneyField(
            fieldKey: const ValueKey<String>('account-amount'),
            controller: _accountAmount,
            label: 'Nasiyaga yoziladi',
            helperText: selected.creditLimit == null
                ? 'Limitsiz hisob'
                : 'Limit ${selected.creditLimit!.format()}, yana ${selected.availableCredit?.format() ?? '0'} mumkin',
            onChanged: (_) => setState(() => _formError = null),
          ),
        ],
        if (_formError != null) ...<Widget>[
          const SizedBox(height: 12),
          Text(_formError!, style: RzType.body.copyWith(color: c.dangerSoftFg)),
        ],
      ],
    );
  }
}
