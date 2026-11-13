import 'package:pos_core/pos_core.dart';

import '../billing/billing_models.dart';
import '../json.dart';

/// Nasiya hisobi (`/pos/customer-accounts*`). [balance] — qarz (musbat).
class PosCustomerAccount {
  const PosCustomerAccount({
    required this.uuid,
    required this.name,
    this.phone,
    this.groupName,
    this.branchId,
    this.creditLimit,
    this.balance = PosMoney.zero,
    this.availableCredit,
    this.isBlocked = false,
    this.note,
    this.createdAt,
    this.updatedAt,
    this.kind = 'customer',
    this.companyName,
    this.monthlyLimit,
    this.chargeApproval = true,
    this.talonMode = 'none',
    this.membersCount = 0,
  });

  factory PosCustomerAccount.fromJson(PosJson j) => PosCustomerAccount(
    uuid: j.str('uuid'),
    name: j.str('name'),
    phone: j.strOrNull('phone'),
    groupName: j.strOrNull('group_name'),
    branchId: j.intOrNull('branch_id'),
    creditLimit: j.moneyOrNull('credit_limit'),
    balance: j.money('balance'),
    availableCredit: j.moneyOrNull('available_credit'),
    isBlocked: j.boolean('is_blocked'),
    note: j.strOrNull('note'),
    createdAt: j.dateOrNull('created_at'),
    updatedAt: j.dateOrNull('updated_at'),
    kind: j.strOrNull('kind') ?? 'customer',
    companyName: j.strOrNull('company_name'),
    monthlyLimit: j.moneyOrNull('monthly_limit'),
    chargeApproval: j.boolean('charge_approval', fallback: true),
    talonMode: j.strOrNull('talon_mode') ?? 'none',
    membersCount: j.intOrNull('members_count') ?? 0,
  );

  final String uuid;
  final String name;
  final String? phone;
  final String? groupName;

  /// `null` — butun do'kon hisobi.
  final int? branchId;

  /// `null` — cheksiz, 0 — nasiya yo'q.
  final PosMoney? creditLimit;

  /// Qarz.
  final PosMoney balance;

  /// Yana qancha nasiya mumkin (`null` — cheksiz).
  final PosMoney? availableCredit;

  /// Yangi nasiya to'xtatilgan (to'lov qabul qilinadi).
  final bool isBlocked;
  final String? note;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  /// P6-44: `customer` (nasiya) yoki `corporate` (korxona: xodimlar talon bilan).
  final String kind;
  final String? companyName;

  /// Korxona: oylik limit (`null` — yo'q).
  final PosMoney? monthlyLimit;

  /// Kassada menejer tasdig'i kerakmi (nasiya — doim; korxona — egasining tanlovi).
  final bool chargeApproval;

  /// `none` | `daily_limit` | `subscription`.
  final String talonMode;
  final int membersCount;

  bool get isCorporate => kind == 'corporate';
}

/// P6-44: kassada topilgan korxona xodimi (`GET /pos/customer-accounts/members/lookup`): hisob, xodim (telefon
/// yashirilgan) va bugun / shu oy qancha qolgani. Internetsiz ishlamaydi (limitlar serverda): kassa naqd yoki karta taklif
/// qiladi.
class PosCorporateLookup {
  const PosCorporateLookup({
    required this.accountUuid,
    required this.accountName,
    required this.memberUuid,
    required this.memberName,
    required this.code,
    required this.businessDate,
    this.companyName,
    this.talonMode = 'none',
    this.chargeApproval = false,
    this.accountBlocked = false,
    this.memberActive = true,
    this.memberPhone,
    this.dailyLimit,
    this.mealsPerDay,
    this.usedToday = PosMoney.zero,
    this.mealsToday = 0,
    this.dailyAvailable,
    this.mealsLeft,
    this.monthUsed = PosMoney.zero,
    this.monthlyLimit,
    this.monthAvailable,
    this.creditAvailable,
  });

  factory PosCorporateLookup.fromJson(PosJson j) {
    final PosJson account = j.obj('account');
    final PosJson member = j.obj('member');
    final PosJson usage = j.obj('usage');
    final PosJson? day = usage.objOrNull('member');
    final PosJson month = usage.obj('month');
    return PosCorporateLookup(
      accountUuid: account.str('uuid'),
      accountName: account.str('name'),
      companyName: account.strOrNull('company_name'),
      talonMode: account.strOrNull('talon_mode') ?? 'none',
      chargeApproval: account.boolean('charge_approval'),
      accountBlocked: account.boolean('is_blocked'),
      memberUuid: member.str('uuid'),
      memberName: member.str('name'),
      memberPhone: member.strOrNull('phone'),
      code: member.str('code'),
      memberActive: member.boolean('is_active', fallback: true),
      dailyLimit: member.moneyOrNull('daily_limit'),
      mealsPerDay: member.intOrNull('meals_per_day'),
      businessDate: usage.str('business_date'),
      usedToday: day?.money('amount') ?? PosMoney.zero,
      mealsToday: day?.intOrNull('meals') ?? 0,
      dailyAvailable: day?.moneyOrNull('daily_available'),
      mealsLeft: day?.intOrNull('meals_left'),
      monthUsed: month.money('amount'),
      monthlyLimit: month.moneyOrNull('monthly_limit'),
      monthAvailable: month.moneyOrNull('available'),
      creditAvailable: usage.moneyOrNull('credit_available'),
    );
  }

  final String accountUuid;
  final String accountName;
  final String? companyName;
  final String talonMode;

  /// `true` — to'lovda menejer tasdig'i ham kerak.
  final bool chargeApproval;
  final bool accountBlocked;
  final String memberUuid;
  final String memberName;

  /// Yashirilgan ("+998 90 *** ** 33").
  final String? memberPhone;
  final String code;
  final bool memberActive;
  final PosMoney? dailyLimit;
  final int? mealsPerDay;

  /// Filialning biznes kuni (YYYY-MM-DD).
  final String businessDate;
  final PosMoney usedToday;
  final int mealsToday;

  /// Bugun yana qancha (`null` — kunlik limit yo'q).
  final PosMoney? dailyAvailable;

  /// Bugun yana necha marta (`null` — cheklanmagan).
  final int? mealsLeft;
  final PosMoney monthUsed;
  final PosMoney? monthlyLimit;
  final PosMoney? monthAvailable;
  final PosMoney? creditAvailable;

  /// [amount] ni yozish mumkinmi (serverdagi qoidalar bilan bir xil; oxirgi so'z — serverniki).
  bool allows(PosMoney amount) =>
      memberActive &&
      !accountBlocked &&
      (dailyAvailable == null || amount <= dailyAvailable!) &&
      (mealsLeft == null || mealsLeft! > 0) &&
      (monthAvailable == null || amount <= monthAvailable!) &&
      (creditAvailable == null || amount <= creditAvailable!);
}

/// Hisob yozuvi: `charge` (+), `repayment` (−), `adjustment` (±).
class PosAccountEntry {
  const PosAccountEntry({
    required this.uuid,
    required this.type,
    required this.amount,
    required this.balanceAfter,
    this.typeLabel,
    this.billUuid,
    this.tender,
    this.fiscalReceiptUuid,
    this.fiscalStatus,
    this.user,
    this.approvedBy,
    this.note,
    this.createdAt,
  });

  factory PosAccountEntry.fromJson(PosJson j) => PosAccountEntry(
    uuid: j.str('uuid'),
    type: j.str('type'),
    typeLabel: j.strOrNull('type_label'),
    amount: j.money('amount'),
    balanceAfter: j.money('balance_after'),
    billUuid: j.strOrNull('bill_uuid'),
    tender: j.strOrNull('tender'),
    fiscalReceiptUuid: j.strOrNull('fiscal_receipt_uuid'),
    fiscalStatus: j.strOrNull('fiscal_status'),
    user: PosRef.maybe(j, 'user'),
    approvedBy: PosRef.maybe(j, 'approved_by'),
    note: j.strOrNull('note'),
    createdAt: j.dateOrNull('created_at'),
  );

  final String uuid;
  final String type;
  final String? typeLabel;
  final PosMoney amount;
  final PosMoney balanceAfter;
  final String? billUuid;
  final String? tender;
  final String? fiscalReceiptUuid;
  final String? fiscalStatus;
  final PosRef? user;
  final PosRef? approvedBy;
  final String? note;
  final DateTime? createdAt;
}

/// Hisob va yozuvlar (`show`: oxirgi 20 ta; `repayments`, `adjustments`: bitta yangi yozuv).
class PosAccountWithEntries {
  const PosAccountWithEntries({
    required this.account,
    this.entries = const <PosAccountEntry>[],
    this.fiscal,
  });

  factory PosAccountWithEntries.fromJson(PosJson j) => PosAccountWithEntries(
    account: PosCustomerAccount.fromJson(j.obj('account')),
    entries: j.has('entry')
        ? <PosAccountEntry>[PosAccountEntry.fromJson(j.obj('entry'))]
        : j.objects('entries', PosAccountEntry.fromJson),
    fiscal: j.has('fiscal') ? PosFiscalState.fromJson(j.obj('fiscal')) : null,
  );

  final PosCustomerAccount account;
  final List<PosAccountEntry> entries;

  /// Qarz to'lovining kredit cheki (P3-26, faqat to'lov javobida).
  final PosFiscalState? fiscal;
}

/// `GET /pos/customer-accounts` sahifasi.
class PosAccountPage {
  const PosAccountPage({
    required this.accounts,
    this.currentPage = 1,
    this.lastPage = 1,
    this.total = 0,
  });

  factory PosAccountPage.fromJson(PosJson j) {
    final PosJson meta =
        j.objOrNull('meta') ?? const PosJson(<String, Object?>{});
    return PosAccountPage(
      accounts: j.objects('accounts', PosCustomerAccount.fromJson),
      currentPage: meta.intOrNull('current_page') ?? 1,
      lastPage: meta.intOrNull('last_page') ?? 1,
      total: meta.intOrNull('total') ?? 0,
    );
  }

  final List<PosCustomerAccount> accounts;
  final int currentPage;
  final int lastPage;
  final int total;
}

/// `GET /pos/customer-accounts/debtors`.
class PosDebtors {
  const PosDebtors({
    required this.accounts,
    this.totalDebt = PosMoney.zero,
    this.count = 0,
  });

  factory PosDebtors.fromJson(PosJson j) => PosDebtors(
    totalDebt: j.money('total_debt'),
    count: j.intOrNull('count') ?? 0,
    accounts: j.objects('accounts', PosCustomerAccount.fromJson),
  );

  final PosMoney totalDebt;
  final int count;
  final List<PosCustomerAccount> accounts;
}

/// `GET /pos/customer-accounts/{uuid}/statement`.
class PosAccountStatement {
  const PosAccountStatement({
    required this.account,
    required this.entries,
    this.from,
    this.to,
    this.openingBalance = PosMoney.zero,
    this.closingBalance = PosMoney.zero,
    this.chargesTotal = PosMoney.zero,
    this.repaymentsTotal = PosMoney.zero,
    this.adjustmentsTotal = PosMoney.zero,
  });

  factory PosAccountStatement.fromJson(PosJson j) => PosAccountStatement(
    account: PosCustomerAccount.fromJson(j.obj('account')),
    entries: j.objects('entries', PosAccountEntry.fromJson),
    from: j.strOrNull('from'),
    to: j.strOrNull('to'),
    openingBalance: j.money('opening_balance'),
    closingBalance: j.money('closing_balance'),
    chargesTotal: j.money('charges_total'),
    repaymentsTotal: j.money('repayments_total'),
    adjustmentsTotal: j.money('adjustments_total'),
  );

  final PosCustomerAccount account;
  final List<PosAccountEntry> entries;
  final String? from;
  final String? to;
  final PosMoney openingBalance;
  final PosMoney closingBalance;
  final PosMoney chargesTotal;
  final PosMoney repaymentsTotal;
  final PosMoney adjustmentsTotal;
}

/// Yangi hisob yoki o'zgartirish (faqat berilgan maydonlar).
class PosAccountInput {
  const PosAccountInput({
    this.name,
    this.phone,
    this.groupName,
    this.creditLimit,
    this.clearCreditLimit = false,
    this.note,
    this.branchId,
    this.isBlocked,
  });

  final String? name;
  final String? phone;
  final String? groupName;
  final PosMoney? creditLimit;

  /// `credit_limit: null` yuborish (cheksiz).
  final bool clearCreditLimit;
  final String? note;
  final int? branchId;
  final bool? isBlocked;

  Map<String, Object?> toJson() => <String, Object?>{
    'name': ?name,
    'phone': ?phone,
    'group_name': ?groupName,
    if (clearCreditLimit)
      'credit_limit': null
    else
      'credit_limit': ?creditLimit?.toApiString(),
    'note': ?note,
    'branch_id': ?branchId,
    'is_blocked': ?isBlocked,
  };
}
