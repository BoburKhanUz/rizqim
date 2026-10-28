import 'dart:async';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:rizqim_api/rizqim_api.dart';

import '../json.dart';
import 'connectivity.dart';
import 'pos_exceptions.dart';

/// Yangi `Idempotency-Key` (UUID v4, `Random.secure`). Har bir **yangi** amalga yangisi; tarmoq xatosidan keyin
/// aynan o'sha amalni qayta yuborishda — o'sha kalit.
String newIdempotencyKey([Random? random]) {
  final Random r = random ?? _secure;
  final List<int> b = List<int>.generate(16, (_) => r.nextInt(256));
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  String hex(int from, int to) => <String>[
    for (var i = from; i < to; i++) b[i].toRadixString(16).padLeft(2, '0'),
  ].join();
  return '${hex(0, 4)}-${hex(4, 6)}-${hex(6, 8)}-${hex(8, 10)}-${hex(10, 16)}';
}

final Random _secure = Random.secure();

/// Muvaffaqiyatli javob: konvertning `data` va `message` qismi.
class PosResponse {
  const PosResponse({
    required this.data,
    required this.message,
    this.statusCode,
  });

  final Object? data;

  /// Server matni ("Taomlar qo'shildi.", "Smena ochildi."): bildirishnoma uchun.
  final String message;
  final int? statusCode;
}

/// POS endpointlari uchun yupqa HTTP qatlami [RizqimClient.dio] ustida (token qo'yish va 401 ni [AuthInterceptor]
/// hal qiladi; xodim PIN sessiyasi tokeni bilan ketadi).
///
/// * Konvert `{success, message, data}` ochiladi.
/// * Har o'zgartiruvchi so'rovga `Idempotency-Key` qo'yiladi (berilmasa yangisi), xatoda u [PosException.idempotencyKey]
///   da qaytadi.
/// * Xatolar turi bo'yicha [PosException] bo'ladi (`version_conflict` → [PosVersionConflict], ...).
/// * 2xx javobni o'qib bo'lmasa — [PosException] `reason: unexpected_response` (amal serverda **bajarilgan**: ilova
///   holatni qayta yuklasin, amalni takrorlamasin).
/// * Har javob yoki tarmoq xatosi [connectivity] ga yoziladi (yuqori paneldagi "Aloqa yo'q" belgisi).
class PosHttp {
  PosHttp(this.dio, {PosConnectivity? connectivity, Random? random})
    : connectivity = connectivity ?? PosConnectivity(),
      _random = random; // ignore: prefer_initializing_formals

  final Dio dio;
  final PosConnectivity connectivity;
  final Random? _random;

  /// Yangi kalit (testlarda [Random] bilan takrorlanadigan).
  String newKey() => newIdempotencyKey(_random);

  /// `GET` va natijani [parse] bilan o'qish.
  Future<T> get<T>(
    String path,
    T Function(Object? data) parse, {
    Map<String, Object?>? query,
    AuthScope? scope,
  }) async {
    final PosResponse r = await raw(
      'GET',
      path,
      query: query,
      scope: scope,
      idempotent: false,
    );
    return decode(r, parse);
  }

  /// O'zgartiruvchi so'rov (`POST`, `PATCH`, `PUT`, `DELETE`) `Idempotency-Key` bilan.
  Future<T> mutate<T>(
    String method,
    String path,
    T Function(Object? data) parse, {
    Object? body,
    String? idempotencyKey,
    Map<String, Object?>? query,
  }) async {
    final PosResponse r = await raw(
      method,
      path,
      body: body,
      query: query,
      idempotencyKey: idempotencyKey,
    );
    return decode(r, parse);
  }

  /// [response] ning `data` qismini [parse] bilan o'qiydi; o'qib bo'lmasa — `unexpected_response`.
  T decode<T>(PosResponse response, T Function(Object? data) parse) {
    try {
      return parse(response.data);
    } on PosException {
      rethrow;
    } on Object catch (e) {
      throw PosException(
        message: "Server javobini o'qib bo'lmadi. Ilovani yangilang yoki administratorga ayting.",
        statusCode: response.statusCode,
        reason: ApiException.unexpectedResponse,
        cause: e,
      );
    }
  }

  /// So'rov yuboradi, konvertni ochadi, xatoni [PosException] ga aylantiradi.
  Future<PosResponse> raw(
    String method,
    String path, {
    Object? body,
    Map<String, Object?>? query,
    AuthScope? scope,
    bool idempotent = true,
    String? idempotencyKey,
  }) async {
    final String? key = idempotent && method != 'GET'
        ? (idempotencyKey ?? newKey())
        : null;
    Options options = Options(
      method: method,
      headers: <String, Object?>{'Idempotency-Key': ?key},
    );
    if (scope != null) options = scoped(scope, options);
    final Response<Object?> response;
    try {
      response = await dio.request<Object?>(
        path,
        data: body,
        queryParameters: query == null
            ? null
            : <String, Object?>{
                for (final MapEntry<String, Object?> e in query.entries)
                  if (e.value != null)
                    e.key: e.value is bool
                        ? ((e.value! as bool) ? 1 : 0)
                        : e.value,
              },
        options: options,
      );
    } on DioException catch (e) {
      final PosException error = mapError(e, idempotencyKey: key);
      if (error is PosNetworkException) {
        connectivity.reportFailure(error);
      } else {
        connectivity.reportReachable();
      }
      throw error;
    }
    connectivity.reportReachable();
    final Object? payload = response.data;
    if (payload is Map) {
      final Object? message = payload['message'];
      return PosResponse(
        data: payload['data'],
        message: message is String ? message : '',
        statusCode: response.statusCode,
      );
    }
    // 204 yoki konvertsiz javob
    return PosResponse(
      data: payload,
      message: '',
      statusCode: response.statusCode,
    );
  }

  /// [DioException] → turi bo'yicha [PosException].
  static PosException mapError(DioException e, {String? idempotencyKey}) {
    final Response<Object?>? response = e.response;
    if (response == null) {
      return PosNetworkException(
        message: "Serverga ulanib bo'lmadi. Internet aloqasini tekshirib, qayta urinib ko'ring.",
        cause: e,
        idempotencyKey: idempotencyKey,
      );
    }
    final ApiException base = ApiException.fromResponse(response, cause: e);
    final Object? body = response.data;
    final Object? rawData = body is Map ? body['data'] : null;
    final Map<String, Object?> data = rawData is Map
        ? rawData.cast<String, Object?>()
        : const <String, Object?>{};
    final int? status = base.statusCode;
    final String? reason = base.reason;
    final String message = _messageFor(base.message, reason, status, body);

    switch ((status, reason)) {
      case (409, PosReasons.versionConflict):
        return PosVersionConflict(
          message: message,
          statusCode: status,
          reason: reason,
          cause: e,
          data: data,
          idempotencyKey: idempotencyKey,
        );
      case (403, PosReasons.approvalRequired):
        return PosApprovalRequired(
          message: message,
          statusCode: status,
          reason: reason,
          cause: e,
          data: data,
          idempotencyKey: idempotencyKey,
        );
      case (403, final String r) when PosReasons.approvalRejections.contains(r):
        return PosApprovalRejected(
          message: message,
          statusCode: status,
          reason: reason,
          cause: e,
          data: data,
          idempotencyKey: idempotencyKey,
        );
      case (403, final String r) when PosReasons.planRefusals.contains(r):
        return PosPlanRefused(
          message: message,
          statusCode: status,
          reason: reason,
          cause: e,
          data: data,
          idempotencyKey: idempotencyKey,
        );
      case (409, _):
        return PosConflict(
          message: message,
          statusCode: status,
          reason: reason,
          cause: e,
          data: data,
          idempotencyKey: idempotencyKey,
        );
      case (422, final String _):
        return PosRejected(
          message: message,
          statusCode: status,
          reason: reason,
          errors: base.errors,
          cause: e,
          data: data,
          idempotencyKey: idempotencyKey,
        );
      case (422, null):
        return PosValidationException(
          message: message,
          statusCode: status,
          errors: base.errors,
          cause: e,
          data: data,
          idempotencyKey: idempotencyKey,
        );
      case (403, _):
        return PosForbidden(
          message: message,
          statusCode: status,
          reason: reason,
          cause: e,
          data: data,
          idempotencyKey: idempotencyKey,
        );
      case (404, _):
        return PosNotFound(
          message: message,
          statusCode: status,
          reason: reason,
          cause: e,
          data: data,
          idempotencyKey: idempotencyKey,
        );
      default:
        return PosException(
          message: message,
          statusCode: status,
          reason: reason,
          errors: base.errors,
          retryAfter: base.retryAfter,
          cause: e,
          data: data,
          idempotencyKey: idempotencyKey,
        );
    }
  }

  static const Set<String> _english = <String>{
    'Unauthenticated.',
    'This action is unauthorized.',
    'Server Error',
    'Not Found',
    'Too Many Attempts.',
    'Forbidden',
    'Unauthorized',
  };

  static String _messageFor(
    String server,
    String? reason,
    int? status,
    Object? body,
  ) {
    final bool hasOwn =
        body is Map && body['message'] is String && server.trim().isNotEmpty;
    final bool generic =
        !hasOwn ||
        _english.contains(server) ||
        server.startsWith('The ') ||
        server.startsWith("So'rov bajarilmadi");
    final String? known = PosReasons.describe(reason);
    if (known != null && PosReasons.clientWorded.contains(reason)) {
      return known;
    }
    if (!generic) return server;
    if (known != null) return known;
    return switch (status) {
      401 => 'Sessiya tugadi. PIN bilan qayta kiring.',
      403 => "Bu amal uchun ruxsat yo'q.",
      404 => "Topilmadi: u o'chirilgan yoki boshqa filialniki.",
      429 => "Juda ko'p urinish. Birozdan keyin qayta urinib ko'ring.",
      final int s when s >= 500 =>
        "Serverda xato yuz berdi. Birozdan keyin qayta urinib ko'ring.",
      _ => hasOwn ? server : "So'rov bajarilmadi ($status).",
    };
  }
}

/// [PosJson.of] — ma'lumot qismini obyekt sifatida.
PosJson dataObject(Object? data) => PosJson.of(data, 'data');
