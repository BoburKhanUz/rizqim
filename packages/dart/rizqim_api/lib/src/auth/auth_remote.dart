import 'package:dio/dio.dart';

import 'api_exception.dart';
import 'models.dart';
import 'pairing_code.dart';

/// So'rov qaysi token bilan yuboriladi (`Options.extra['rizqim.scope']`). Odatda yozish shart emas: yo'l bo'yicha
/// [AuthInterceptor] o'zi tanlaydi.
enum AuthScope {
  /// Xodim sessiyasi, bo'lmasa shaxsiy ilova tokeni.
  current,

  /// Terminalning o'z tokeni (`/terminal*`, `/devices/{uuid}/staff-session`).
  terminal,

  /// Token yuborilmaydi (kirish, ulash, refresh).
  none,
}

const String scopeExtraKey = 'rizqim.scope';

Options scoped(AuthScope scope, [Options? options]) =>
    (options ?? Options()).copyWith(
      extra: <String, Object?>{...?options?.extra, scopeExtraKey: scope},
    );

/// Kod noto'g'ri shaklda: serverga yuborilmaydi.
class InvalidPairingCodeException implements Exception {
  const InvalidPairingCodeException();

  String get message =>
      "Kod 8 ta belgidan iborat bo'lishi kerak (masalan, ABCD-EFGH).";

  @override
  String toString() => 'InvalidPairingCodeException';
}

/// Kirish endpointlariga qo'lda yozilgan, yupqa HTTP qatlami. Javob konverti `{success, message, data}` ochiladi,
/// xatolar [ApiException] bo'ladi.
class AuthRemote {
  AuthRemote(this._dio);

  final Dio _dio;

  /// `POST /v1/devices/pair`. Kod chiziqcha bilan yoki usiz, har qanday harf bilan berilishi mumkin.
  Future<PairedTerminal> pair(
    String code, {
    String? name,
    String? platform,
    String? appVersion,
  }) async {
    final String? normalized = PairingCode.normalize(code);
    if (normalized == null) throw const InvalidPairingCodeException();
    final Map<String, Object?> data = await _send(
      () => _dio.post<Object?>(
        '/v1/devices/pair',
        data: <String, Object?>{
          'code': normalized,
          'name': ?name,
          'platform': ?platform,
          'app_version': ?appVersion,
        },
        options: scoped(AuthScope.none),
      ),
    );
    return PairedTerminal.fromJson(data);
  }

  /// `GET /v1/terminal/staff` (terminal tokeni bilan): PIN klaviaturasi uchun xodimlar.
  Future<List<StaffMember>> terminalStaff() async {
    final Response<Object?> response = await _raw(
      () => _dio.get<Object?>(
        '/v1/terminal/staff',
        options: scoped(AuthScope.terminal),
      ),
    );
    final Object? data = (response.data as Map<Object?, Object?>?)?['data'];
    return ((data as List<Object?>?) ?? const <Object?>[])
        .map(
          (Object? e) => StaffMember.fromJson(
            (e! as Map<Object?, Object?>).cast<String, Object?>(),
          ),
        )
        .toList();
  }

  /// `POST /v1/devices/{uuid}/staff-session` (terminal tokeni bilan). [userId] yoki [staffCode] dan biri kerak.
  Future<StaffSession> startStaffSession(
    String deviceUuid, {
    int? userId,
    String? staffCode,
    required String pin,
  }) async {
    assert(
      userId != null || staffCode != null,
      'user_id yoki staff_code kerak',
    );
    final Map<String, Object?> data = await _send(
      () => _dio.post<Object?>(
        '/v1/devices/$deviceUuid/staff-session',
        data: <String, Object?>{
          'user_id': ?userId,
          'staff_code': ?staffCode,
          'pin': pin,
        },
        options: scoped(AuthScope.terminal),
      ),
    );
    return StaffSession.fromJson(data);
  }

  /// `DELETE /v1/devices/{uuid}/staff-session` (terminal tokeni bilan).
  Future<void> endStaffSession(String deviceUuid) async {
    await _raw(
      () => _dio.delete<Object?>(
        '/v1/devices/$deviceUuid/staff-session',
        options: scoped(AuthScope.terminal),
      ),
    );
  }

  /// `POST /v1/auth/refresh`: eski refresh token sarflanadi, yangi juftlik qaytadi. Faqat [AuthSession] chaqiradi
  /// (bir vaqtda bittadan: single-flight).
  Future<UserSession> refresh({
    required String refreshToken,
    required String deviceUuid,
  }) async {
    final Map<String, Object?> data = await _send(
      () => _dio.post<Object?>(
        '/v1/auth/refresh',
        data: <String, Object?>{
          'refresh_token': refreshToken,
          'device_uuid': deviceUuid,
        },
        options: scoped(AuthScope.none),
      ),
    );
    // Javobdagi `device.uuid` bo'lmasa ham, qurilma o'zgarmaydi
    return UserSession.fromJson(<String, Object?>{
      'device_uuid': deviceUuid,
      ...data,
    });
  }

  /// `POST /v1/auth/logout` (joriy sessiya tokeni bilan).
  Future<void> logout() async {
    await _raw(() => _dio.post<Object?>('/v1/auth/logout'));
  }

  Future<Map<String, Object?>> _send(
    Future<Response<Object?>> Function() call,
  ) async {
    final Response<Object?> response = await _raw(call);
    final Object? data = (response.data as Map<Object?, Object?>?)?['data'];
    if (data is! Map) {
      throw ApiException(
        message: "Server kutilmagan javob qaytardi.",
        statusCode: response.statusCode,
      );
    }
    return data.cast<String, Object?>();
  }

  Future<Response<Object?>> _raw(
    Future<Response<Object?>> Function() call,
  ) async {
    try {
      return await call();
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }
}
