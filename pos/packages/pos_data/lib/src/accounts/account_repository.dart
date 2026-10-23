import 'package:pos_core/pos_core.dart';

import '../http/pos_http.dart';
import 'account_models.dart';

/// Nasiya hisoblari (docs/API.md, "Nasiya hisoblari"). Yupqa qatlam: P3-22 kengaytiradi.
class CustomerAccountRepository {
  CustomerAccountRepository(this._http);

  final PosHttp _http;

  static PosCustomerAccount _account(Object? data) =>
      PosCustomerAccount.fromJson(dataObject(data));
  static PosAccountWithEntries _withEntries(Object? data) =>
      PosAccountWithEntries.fromJson(dataObject(data));

  /// Qidiruv (ism, guruh, telefon), sahifalangan.
  Future<PosAccountPage> list({
    String? query,
    bool debtorsOnly = false,
    int? perPage,
    int? page,
  }) => _http.get(
    '/v1/pos/customer-accounts',
    (Object? data) => PosAccountPage.fromJson(dataObject(data)),
    query: <String, Object?>{
      'q': query,
      if (debtorsOnly) 'debtors': true,
      'per_page': perPage,
      'page': page,
    },
  );

  /// Qarzdorlar (eng katta qarz birinchi) va jami qarz.
  Future<PosDebtors> debtors() => _http.get(
    '/v1/pos/customer-accounts/debtors',
    (Object? data) => PosDebtors.fromJson(dataObject(data)),
  );

  /// Hisob va oxirgi 20 yozuv.
  Future<PosAccountWithEntries> get(String uuid) =>
      _http.get('/v1/pos/customer-accounts/$uuid', _withEntries);

  /// Ko'chirma (`from`, `to` — `YYYY-MM-DD`).
  Future<PosAccountStatement> statement(
    String uuid, {
    String? from,
    String? to,
  }) => _http.get(
    '/v1/pos/customer-accounts/$uuid/statement',
    (Object? data) => PosAccountStatement.fromJson(dataObject(data)),
    query: <String, Object?>{'from': from, 'to': to},
  );

  /// Qarz to'lovi (kredit cheki chiqadi). `Idempotency-Key` majburiy.
  Future<PosAccountWithEntries> repay(
    String uuid, {
    required PosMoney amount,
    required String tender,
    String? cardType,
    String? transactionId,
    String? note,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/customer-accounts/$uuid/repayments',
    _withEntries,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'amount': amount.toApiString(),
      'tender': tender,
      'card_type': ?cardType,
      'transaction_id': ?transactionId,
      'note': ?note,
    },
  );

  /// Hisob ochish (`customer_accounts.manage`: ega, menejer; P3-26: kassir menejer tasdig'i [approvalToken] bilan).
  Future<PosCustomerAccount> create(
    PosAccountInput input, {
    String? approvalToken,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/customer-accounts',
    _account,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      ...input.toJson(),
      'approval_token': ?approvalToken,
    },
  );

  /// Hisobni o'zgartirish (ruxsat — [create] dagidek).
  Future<PosCustomerAccount> update(
    String uuid,
    PosAccountInput changes, {
    String? approvalToken,
    String? idempotencyKey,
  }) => _http.mutate(
    'PATCH',
    '/v1/pos/customer-accounts/$uuid',
    _account,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      ...changes.toJson(),
      'approval_token': ?approvalToken,
    },
  );

  /// Tuzatish: [amount] musbat — qarzga qo'shadi, manfiy — kechadi; [note] majburiy.
  Future<PosAccountWithEntries> adjust(
    String uuid, {
    required PosMoney amount,
    required String note,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/customer-accounts/$uuid/adjustments',
    _withEntries,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{'amount': amount.toApiString(), 'note': note},
  );
}
