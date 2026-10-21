import 'package:dio/dio.dart';

/// Backend rad etgan kirish so'rovining `data.reason` kodlari (docs/AUTH.md).
abstract final class AuthReason {
  static const String refreshReused = 'refresh_reused';
  static const String refreshInvalid = 'refresh_invalid';
  static const String refreshExpired = 'refresh_expired';
  static const String pairingInvalid = 'pairing_invalid';
  static const String staffSessionNotAllowed = 'staff_session_not_allowed';
  static const String pinInvalid = 'pin_invalid';
  static const String pinLocked = 'pin_locked';

  /// Refresh token endi hech qachon ishlamaydi: foydalanuvchi qaytadan kirishi kerak.
  static const Set<String> refreshDead = <String>{
    refreshReused,
    refreshInvalid,
    refreshExpired,
  };
}

/// Backend javobidagi xato (`{success: false, message, data: {reason, retry_after}, errors}`) yoki tarmoq xatosi.
class ApiException implements Exception {
  const ApiException({
    required this.message,
    this.statusCode,
    this.reason,
    this.errors = const <String, List<String>>{},
    this.retryAfter,
    this.cause,
  });

  /// Foydalanuvchiga ko'rsatsa bo'ladigan matn (backend o'zbekcha yozadi).
  final String message;

  /// HTTP kod; tarmoq xatosida `null`.
  final int? statusCode;

  /// `data.reason`, masalan `pairing_invalid`.
  final String? reason;

  /// Maydon bo'yicha validatsiya xatolari (422).
  final Map<String, List<String>> errors;

  /// 429 da qancha kutish kerak (`Retry-After` yoki `data.retry_after`).
  final Duration? retryAfter;

  final Object? cause;

  /// `data.reason`: server javob berdi (2xx), lekin ilova uni o'qiy olmadi (spetsifikatsiyaga mos emas). Bu "aloqa yo'q"
  /// emas: ilova yoki server versiyasi mos kelmaydi.
  static const String unexpectedResponse = 'unexpected_response';

  /// Server javobini o'qib bo'lmadi ([unexpectedResponse]).
  bool get isUnexpectedResponse => reason == unexpectedResponse;

  /// Server bilan aloqa bo'lmadi (javob umuman kelmadi). O'qib bo'lmagan javob bunga kirmaydi.
  bool get isNetworkError => statusCode == null && !isUnexpectedResponse;
  bool get isUnauthorized => statusCode == 401;
  bool get isRateLimited => statusCode == 429;

  /// Refresh token o'lgan (qayta ishlatilgan, noto'g'ri yoki muddati o'tgan).
  bool get isRefreshDead =>
      statusCode == 401 && AuthReason.refreshDead.contains(reason);

  /// [error] dan: [ApiException] o'zi, [DioException] (javobi bo'lsa tahlil qilinadi), boshqa narsa — umumiy xato.
  static ApiException from(Object error) {
    if (error is ApiException) return error;
    if (error is DioException) {
      final Response<Object?>? response = error.response;
      if (response == null) {
        return ApiException(message: "Serverga ulanib bo'lmadi.", cause: error);
      }
      return fromResponse(response, cause: error);
    }
    return ApiException(message: error.toString(), cause: error);
  }

  static ApiException fromResponse(
    Response<Object?> response, {
    Object? cause,
  }) {
    final Object? body = response.data;
    var message = "So'rov bajarilmadi (${response.statusCode}).";
    String? reason;
    var errors = const <String, List<String>>{};
    Duration? retryAfter;
    if (body is Map) {
      final Object? m = body['message'];
      if (m is String && m.isNotEmpty) message = m;
      final Object? data = body['data'];
      if (data is Map) {
        final Object? r = data['reason'];
        if (r is String) reason = r;
        final Object? ra = data['retry_after'];
        if (ra is num) retryAfter = Duration(seconds: ra.toInt());
      }
      final Object? e = body['errors'];
      if (e is Map) {
        errors = <String, List<String>>{
          for (final MapEntry<Object?, Object?> entry in e.entries)
            if (entry.value is List)
              '${entry.key}': (entry.value! as List<Object?>)
                  .map((Object? v) => '$v')
                  .toList(),
        };
      }
    }
    final String? header = response.headers.value('retry-after');
    if (header != null) {
      final int? seconds = int.tryParse(header);
      if (seconds != null) retryAfter = Duration(seconds: seconds);
    }
    return ApiException(
      message: message,
      statusCode: response.statusCode,
      reason: reason,
      errors: errors,
      retryAfter: retryAfter,
      cause: cause,
    );
  }

  @override
  String toString() =>
      'ApiException($statusCode${reason != null ? ', $reason' : ''}): $message';
}
