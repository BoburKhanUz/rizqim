import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/destinations.dart';
import '../../shell/limited_mode.dart';
import '../../shell/plan_gate.dart';
import '../../shell/pos_scope.dart';
import '../../counter/counter_format.dart';
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
  /// Javobsiz qolgan to'lov: aynan o'zi (kalit, tasdiq) qayta yuboriladi.
  final LostRequest _lost = LostRequest();

  /// To'landi, chek kutilmoqda.
  PosBillResult? _pending;
  PosException? _blocking;

  // P6-22: choy puli (shu formadagi to'lov ustiga) va kimga (shaxsiy rejim)
  late final TextEditingController _tip = TextEditingController();
  int? _tipStaffId;

  /// Review P6 wave 2 (S5): the manager's approval for giving the tip to [_tipApprovalFor] (not the order's waiter).
  String? _tipApproval;
  int? _tipApprovalFor;

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

  /// P6-22: choy puli olinadimi (`tips` moduli, bootstrap `tips.enabled`).
  PosTipSettings get _tips {
    final PosSession session = _scope.session;
    final PosTipSettings t = session.bootstrap?.tips ?? const PosTipSettings();
    return t.enabled && session.hasModule(PosModule.tips)
        ? t
        : const PosTipSettings();
  }

  /// Qo'shilgan to'lovlar va joriy formadagi choy puli (chekka kirmaydi).
  PosMoney get _tipsTotal => PosMoney.sum(<PosMoney>[
    for (final _Part p in _parts) p.input.tip ?? PosMoney.zero,
    if (_tabTakesTip) _draft.effectiveTip,
  ]);

  bool get _tabTakesTip =>
      _tips.enabled &&
      (_tab == PosTender.cash ||
          _tab == PosTender.card ||
          _tab == PosTender.qr);

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
    _tipStaffId = widget.order?.waiter?.id;
    if (_depositAvailable.isPositive) {
      // Avans bo'lsa birinchi taklif qilinadi (POS_SPEC 2.9)
      _tab = PosTender.prepayment;
      _prepay.text = moneyText(_min(_depositAvailable, _remaining));
    }
  }

  @override
  void dispose() {
    _tip.dispose();
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
          _draft = TenderDraft(
            tender: t,
            amount: _remaining,
            tip: parseMoney(_tip.text),
          );
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
            'Karta (${cardTypes[_draft.cardType] ?? _draft.cardType}) · ${_draft.transactionId.trim()}${_draft.cardLast4.trim().isEmpty ? '' : ' · ••••${_draft.cardLast4.trim()}'}',
          PosTender.cash when _draft.tendered != null =>
            'Naqd · berildi ${_draft.tendered!.formatAmount()}, qaytim ${_draft.change.formatAmount()}',
          _ => _tab.label,
        };
        final PosMoney tip = _draft.effectiveTip;
        return _Part(
          _draft.toInput(),
          tip.isPositive ? '$label · choy puli ${tip.formatAmount()}' : label,
        );
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
      _tip.clear();
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
    // P6-22: whom the tips go to (personal mode: the picked staff member, by default the order's waiter)
    final bool anyTip = tenders.any(
      (PosTenderInput t) => t.tip?.isPositive ?? false,
    );
    int? tipStaff = anyTip && _tips.picksStaff ? _tipStaffId : null;
    // Review P6 wave 2 (S5): someone other than the order's waiter needs tips.manage or a manager's PIN; offline
    // there is no approval — the tip goes to the waiter
    final int? waiterId = widget.order?.waiter?.id;
    String? tipToken;
    if (tipStaff != null &&
        tipStaff != waiterId &&
        !_scope.session.can(PosPermissions.tipsManage)) {
      if (limitedMode(context)) {
        tipStaff = waiterId;
        showPosMessage(
          context,
          "Internetsiz menejer tasdig'i yo'q: choy puli buyurtma ofitsiantiga yoziladi.",
        );
      } else {
        if (_tipApproval == null || _tipApprovalFor != tipStaff) {
          final ApprovalToken? ok = await showManagerApproval(
            context,
            permission: PosPermissions.tipsManage,
            reason: "Choy pulini buyurtma ofitsiantidan boshqa xodimga berish uchun menejer tasdig'i kerak.",
          );
          if (ok == null || !mounted) return;
          _tipApproval = ok.token;
          _tipApprovalFor = tipStaff;
        }
        tipToken = _tipApproval;
      }
    }
    final PosMoney tipped = PosMoney.sum(<PosMoney>[
      for (final PosTenderInput t in tenders) t.tip ?? PosMoney.zero,
    ]);
    final String signature =
        '${tenders.map((PosTenderInput t) => t.toJson().toString()).join('|')}|$tipStaff|$tipToken';
    setState(() {
      _busy = true;
      _blocking = null;
    });
    try {
      final PosBillResult r = await _lost.run<PosBillResult>(
        context,
        signature,
        (String? token, String? key) => _scope.data.billing.pay(
          _bill.uuid,
          tenders: tenders,
          shiftUuid: _scope.session.shift?.uuid,
          approvalToken: token,
          idempotencyKey: key,
          tipStaffId: tipStaff,
          tipApprovalToken: tipToken,
        ),
      );
      _tipApproval = null;
      _tipApprovalFor = null;
      if (!mounted) return;
      _bill = r.bill;
      _parts.clear();
      _tip.clear();
      if (tipped.isPositive) {
        showPosMessage(
          context,
          "Choy puli ${tipped.format()} yozildi (chekka kirmaydi).",
        );
      }
      final bool cash = tenders.any(
        (PosTenderInput t) => t.tender == PosTender.cash,
      );
      await _afterPayment(r, openDrawer: cash);
    } on PosNetworkException {
      if (mounted) {
        showPosMessage(
          context,
          "Aloqa yo'q: to'lov holati noma'lum. Xuddi shu to'lovni qayta yuborish xavfsiz — ikki marta yozilmaydi.",
        );
      }
    } on PosConflict catch (e) {
      if (e.reason == 'shift_not_open' || e.reason == PosReasons.shiftClosed) {
        // P3-35: closed by a Z meanwhile, no money taken: the shift is read again, a new one is needed
        if (e.reason == PosReasons.shiftClosed) unawaited(reloadShift(_scope));
        if (mounted) setState(() => _blocking = e);
      } else if (mounted) {
        showPosError(context, e);
      }
    } on Object catch (e) {
      // An expired or used approval is asked for again on the next try
      if (e is PosApprovalRejected) {
        _tipApproval = null;
        _tipApprovalFor = null;
      }
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
    if (r.bill.fiscal.status == 'offline') {
      // P3-31: the money is taken offline; the sale receipt is registered and printed once the till is back online
      if (openDrawer) printer.openDrawer();
      showPosMessage(
        context,
        "To'lov olindi (internetsiz). Fiskal chek internet tiklangach chiqadi va chop etiladi.",
      );
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
    if (!ensurePlan(context, PosFeature.customerAccounts)) return;
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
              // P6-22: tips are on top of the bill and never on the receipt: shown apart
              if (_tipsTotal.isPositive) ...<Widget>[
                AmountRow(
                  label: 'Choy puli (chekka kirmaydi)',
                  amount: _tipsTotal,
                  tone: StatusTone.info,
                ),
                Text(
                  'Chek: ${(_added + (_formHasAmount && remaining.isPositive ? _formAmount : PosMoney.zero)).format()}, '
                  'choy puli: ${_tipsTotal.format()}',
                  key: const ValueKey<String>('payment-tip-summary'),
                  style: RzType.footnote.copyWith(color: c.fgMuted),
                ),
              ],
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
    // P3-31: offline only cash and a separate card terminal (POS_SPEC 5.1)
    final bool limited = limitedMode(context);
    if (limited && _tab != PosTender.cash && _tab != PosTender.card) {
      _tab = PosTender.cash;
    }
    return PosCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          ChoiceRow<PosTender>(
            options: <PosTender, String>{
              PosTender.cash: 'Naqd',
              PosTender.card: 'Karta',
              if (!limited) PosTender.qr: 'QR',
              if (!limited && _depositAvailable.isPositive)
                PosTender.prepayment: 'Avans',
              // P6-11: nasiya only where the format has customer accounts
              if (!limited &&
                  PosScope.of(context).session
                      .hasModule(PosModule.customerAccounts))
                PosTender.account: 'Nasiya',
            },
            selected: _tab,
            onSelected: _selectTab,
          ),
          if (limited) ...<Widget>[
            const SizedBox(height: 12),
            const NoticeBanner(
              icon: Icons.cloud_off_rounded,
              message: "Internetsiz rejim: faqat naqd va karta. QR, avans va nasiya internet tiklangach. Fiskal chek internet tiklangach chiqadi.",
            ),
          ],
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
          if (_tabTakesTip) _tipSection(c),
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

  /// P6-22: choy puli — summa, (naqd) "Qaytimni choy puli qilish", shaxsiy rejimda kimga (sukut — buyurtma
  /// ofitsianti). Choy puli schyotdan tashqari: fiskal chekka kirmaydi.
  Widget _tipSection(RzColors c) {
    final PosTipSettings settings = _tips;
    final bool cash = _tab == PosTender.cash;
    final PosMoney tip = _draft.effectiveTip;
    // Review P6 wave 2 (S5): only the branch's tip roles (waiters by default) may take a tip
    final List<PosStaffRef> staff = <PosStaffRef>[
      ...?_scope.session.bootstrap?.staff.where(
        (PosStaffRef s) => s.role == 'waiter' && settings.takes(s.role),
      ),
      ...?_scope.session.bootstrap?.staff.where(
        (PosStaffRef s) =>
            s.role != 'waiter' && s.role != 'owner' && settings.takes(s.role),
      ),
    ];
    final PosRef? waiter = widget.order?.waiter;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        const Divider(height: 32),
        Row(
          children: <Widget>[
            Icon(Icons.volunteer_activism_outlined, color: c.fgMuted),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Choy puli',
                style: RzType.headline.copyWith(color: c.fg),
              ),
            ),
            Flexible(
              child: Text(
                'chekka kirmaydi',
                textAlign: TextAlign.right,
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        MoneyField(
          fieldKey: const ValueKey<String>('tip-amount'),
          controller: _tip,
          label: 'Choy puli summasi',
          helperText: cash && _draft.tipFromChange && _draft.tip == null
              ? "Bo'sh — butun qaytim choy puliga"
              : null,
          onChanged: (PosMoney? m) => setState(() {
            _draft.tip = m;
            _formError = null;
          }),
        ),
        if (cash)
          SwitchListTile(
            key: const ValueKey<String>('tip-from-change'),
            contentPadding: EdgeInsets.zero,
            title: const Text('Qaytimni choy puli qilish'),
            subtitle: _draft.tendered == null
                ? const Text('Mijoz bergan pulni kiriting')
                : null,
            value: _draft.tipFromChange,
            onChanged: (bool v) => setState(() {
              _draft.tipFromChange = v;
              _formError = null;
            }),
          ),
        if (tip.isPositive && settings.mode == PosTipMode.pool)
          Text(
            "Umumiy qozonga: kun (smena) oxirida qoidaga ko'ra bo'linadi.",
            style: RzType.footnote.copyWith(color: c.fgMuted),
          ),
        if (tip.isPositive && settings.picksStaff) ...<Widget>[
          const SizedBox(height: 8),
          Text('Kimga', style: RzType.footnote.copyWith(color: c.fgMuted)),
          const SizedBox(height: 6),
          ChoiceRow<int>(
            key: const ValueKey<String>('tip-staff'),
            options: <int, String>{
              if (waiter != null &&
                  !staff.any((PosStaffRef s) => s.id == waiter.id))
                waiter.id: waiter.name ?? 'Ofitsiant',
              for (final PosStaffRef s in staff) s.id: s.name,
            },
            selected: _tipStaffId,
            onSelected: (int id) => setState(() => _tipStaffId = id),
          ),
          if (_tipStaffId == null)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                "Xodim tanlanmasa choy puli hech kimga biriktirilmaydi (menejer keyin beradi).",
                style: RzType.footnote.copyWith(color: c.warningSoftFg),
              ),
            )
          else if (_tipStaffId != waiter?.id &&
              !_scope.session.can(PosPermissions.tipsManage))
            Padding(
              key: const ValueKey<String>('tip-staff-approval-hint'),
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                "Buyurtma ofitsiantidan boshqa xodimga — menejer tasdig'i bilan.",
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
            ),
        ],
      ],
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
    // Without the permission the account is opened with a manager's PIN
    final bool canCreate =
        session.can(PosPermissions.customerAccountsManage) ||
        session.needsApproval(PosPermissions.customerAccountsManage);
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
            // P4-21: opening a new account is the plan's customer_accounts; paying the bill to an account that
            // exists goes on whatever the plan
            child: PlanTextButton(
              feature: PosFeature.customerAccounts,
              icon: Icons.person_add_alt_1_rounded,
              label: 'Yangi hisob ochish',
              onPressed: _createAccount,
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
