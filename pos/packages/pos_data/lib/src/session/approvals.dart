import 'package:rizqim_api/rizqim_api.dart';

import '../http/pos_exceptions.dart';
import '../http/pos_http.dart';
import '../json.dart';

/// Menejer tasdig'i talab qilinadigan ruxsatlar (`POST /pos/approvals` `permission`).
abstract final class PosPermissions {
  static const String ordersView = 'orders.view';
  static const String ordersManage = 'orders.manage';
  static const String ordersCancel = 'orders.cancel';
  static const String ordersDiscount = 'orders.discount';
  static const String ordersVoidItem = 'orders.void_item';
  static const String tablesManage = 'tables.manage';
  static const String openShift = 'pos.open_shift';
  static const String closeShift = 'pos.close_shift';
  static const String cashInOut = 'pos.cash_in_out';
  static const String takePayment = 'pos.take_payment';
  static const String reprintBill = 'pos.reprint_bill';
  static const String refund = 'pos.refund';
  static const String bookingsManage = 'bookings.manage';
  static const String customerAccountsManage = 'customer_accounts.manage';

  /// P6-15/P6-19: qozon yoki partiyani hisobdan chiqarish (kassirda yo'q — menejer PIN'i bilan).
  static const String batchesManage = 'batches.manage';
  static const String devicesManage = 'devices.manage';
  static const String menuView = 'menu.view';
  static const String reportsView = 'reports.view';

  /// Tasdiq bilan berilishi mumkin bo'lganlari.
  static const Set<String> approvable = <String>{
    ordersManage,
    ordersCancel,
    ordersDiscount,
    ordersVoidItem,
    tablesManage,
    openShift,
    closeShift,
    cashInOut,
    takePayment,
    reprintBill,
    refund,
    bookingsManage,
    customerAccountsManage,
    batchesManage,
  };

  /// Ruxsatning o'zbekcha nomi (tasdiq oynasi sarlavhasi uchun).
  static String label(String permission) => switch (permission) {
    ordersManage => "Buyurtmani o'zgartirish",
    ordersCancel => 'Buyurtmani bekor qilish',
    ordersDiscount => 'Chegirma yoki xizmat haqini olib tashlash',
    ordersVoidItem => "Yuborilgan taomni o'chirish",
    tablesManage => "Stolni ko'chirish yoki birlashtirish",
    openShift => 'Smena ochish',
    closeShift => 'Smena yopish',
    cashInOut => 'Kassaga kirim yoki chiqim',
    takePayment => "To'lov qabul qilish",
    reprintBill => 'Schyotni qayta chiqarish',
    refund => 'Pul qaytarish',
    bookingsManage => 'Bronni boshqarish',
    customerAccountsManage => 'Nasiya hisobi',
    batchesManage => 'Taomni hisobdan chiqarish',
    _ => permission,
  };
}

/// `POST /pos/approvals` natijasi: bir martalik token — faqat shu ruxsat va shu terminal uchun, 60 soniya.
class ApprovalToken {
  const ApprovalToken({
    required this.token,
    required this.permission,
    required this.expiresAt,
    required this.approvedBy,
    this.approverRole,
  });

  factory ApprovalToken.fromJson(PosJson j) {
    final PosJson by = j.obj('approved_by');
    return ApprovalToken(
      token: j.str('token'),
      permission: j.str('permission'),
      expiresAt: j.date('expires_at'),
      approvedBy: PosRef.fromJson(by),
      approverRole: by.strOrNull('role'),
    );
  }

  final String token;
  final String permission;
  final DateTime expiresAt;
  final PosRef approvedBy;
  final String? approverRole;

  bool isExpired(DateTime now) => !expiresAt.isAfter(now);
}

/// Foydalanuvchi tasdiq oynasini yopdi ([runWithApproval]).
class PosApprovalCancelled extends PosException {
  const PosApprovalCancelled({super.data})
    : super(message: 'Tasdiq bekor qilindi.', reason: 'approval_cancelled');
}

/// Menejer tasdig'i: terminaldagi xodimda ruxsat yo'q bo'lsa, menejer (ega) shu terminalda o'z PIN'ini teradi.
///
/// ```dart
/// final ApprovalToken t = await approvals.request(permission: 'orders.void_item', userId: managerId, pin: '5926');
/// await orders.voidItem(uuid, itemId, version: v, reason: 'xato', approvalToken: t.token);
/// ```
/// Xatolar: noto'g'ri PIN — [PosRejected] `pin_invalid`, bloklangan — [PosException] 429 `pin_locked`
/// (`retryAfter`), menejerda ruxsat yo'q — [PosApprovalRejected] `approver_not_allowed`. UI matni uchun —
/// [approvalFailure].
class ApprovalService {
  ApprovalService(this._http);

  final PosHttp _http;

  /// Tasdiqlay oladigan xodimlar (`GET /terminal/staff?role=manager,owner`, terminal tokeni bilan; PIN
  /// o'rnatilganlari).
  Future<List<StaffMember>> approvers({
    List<String> roles = const <String>['manager', 'owner'],
  }) => _http.get(
    '/v1/terminal/staff',
    (Object? data) => <StaffMember>[
      for (final Object? e in (data as List<Object?>?) ?? const <Object?>[])
        StaffMember.fromJson(PosJson.of(e).map),
    ],
    query: <String, Object?>{'role': roles.join(',')},
    scope: AuthScope.terminal,
  );

  /// `POST /pos/approvals {permission, user_id | staff_code, pin}`.
  Future<ApprovalToken> request({
    required String permission,
    int? userId,
    String? staffCode,
    required String pin,
  }) {
    assert(
      userId != null || staffCode != null,
      'user_id yoki staff_code kerak',
    );
    return _http.mutate(
      'POST',
      '/v1/pos/approvals',
      (Object? data) => ApprovalToken.fromJson(dataObject(data)),
      body: <String, Object?>{
        'permission': permission,
        'user_id': ?userId,
        'staff_code': ?staffCode,
        'pin': pin,
      },
    );
  }
}

/// Tasdiq so'rovi rad etilganda ko'rsatiladigan matn (va bloklangan bo'lsa qancha kutish). [PinFailure] ustida:
/// menejerda ruxsat yo'qligi va terminal talabi alohida.
PinFailure approvalFailure(ApiException e) {
  if (e.reason == PosReasons.approverNotAllowed) {
    return PinFailure(PosReasons.describe(PosReasons.approverNotAllowed)!);
  }
  if (e.reason == PosReasons.terminalRequired) {
    return PinFailure(PosReasons.describe(PosReasons.terminalRequired)!);
  }
  return PinFailure.from(e);
}

/// Amalni bajaradi; server [PosApprovalRequired] qaytarsa [approve] dan token so'raydi va amalni token bilan qayta
/// chaqiradi (yangi `Idempotency-Key` bilan: tana o'zgardi). Token eskirgan yoki ishlatilgan bo'lsa
/// ([PosApprovalRejected]: `approval_expired`, `approval_used`) yana so'raydi — ko'pi bilan [maxPrompts] marta.
///
/// [approve] `null` qaytarsa (oyna yopildi) — [PosApprovalCancelled].
Future<T> runWithApproval<T>(
  Future<T> Function(String? approvalToken) action, {
  required Future<ApprovalToken?> Function(
    String permission,
    PosException cause,
  )
  approve,
  int maxPrompts = 3,
}) async {
  try {
    return await action(null);
  } on PosApprovalRequired catch (first) {
    PosException cause = first;
    final String permission = first.permission ?? PosPermissions.ordersManage;
    for (var prompt = 0; prompt < maxPrompts; prompt++) {
      final ApprovalToken? token = await approve(permission, cause);
      if (token == null) throw PosApprovalCancelled(data: first.data);
      try {
        return await action(token.token);
      } on PosApprovalRejected catch (e) {
        if (e.reason != PosReasons.approvalExpired &&
            e.reason != PosReasons.approvalUsed) {
          rethrow;
        }
        cause = e;
      }
    }
    throw cause;
  }
}
