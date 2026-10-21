import 'package:dio/dio.dart';

import 'api_exception.dart';
import 'auth_remote.dart';
import 'auth_session.dart';

const String _retriedKey = 'rizqim.retried';
const String _sentTokenKey = 'rizqim.sentToken';
const String _sentKindKey = 'rizqim.sentKind';

enum _TokenKind { terminal, staff, user }

/// Har so'rovga to'g'ri tokenni qo'yadi va 401 bo'lsa tokenlarni yangilab bir marta qayta yuboradi.
///
/// Qaysi token: [AuthScope] (`Options.extra`) yoki, berilmasa, yo'l bo'yicha:
/// * `/v1/terminal*`, `/v1/devices/{uuid}/staff-session` — terminal tokeni;
/// * `/v1/devices/pair`, `/v1/auth/refresh|telegram`, `/v1/auth/phone/send-code|verify|select-account` — token yo'q;
/// * boshqasi — xodim sessiyasi, bo'lmasa shaxsiy ilova tokeni. Terminal tokeni oddiy API'ga hech qachon yuborilmaydi.
///
/// 401 bo'lganda:
/// * shaxsiy ilova tokeni: refresh (single-flight, [AuthSession.refresh]) va so'rovni yangi token bilan qayta yuborish.
///   Agar shu orada boshqa so'rov tokenni allaqachon yangilagan bo'lsa, refresh qilinmaydi, so'rov yangi token bilan
///   qayta yuboriladi. Qayta yuborilgan so'rov yana 401 olsa, xato o'tkazib yuboriladi (aylanma yo'q);
/// * xodim tokeni: sessiya yopiladi ([AuthEventKind.staffSignedOut]), xato o'tkazib yuboriladi;
/// * terminal tokeni: terminal o'chirilgan deb olinadi ([AuthEventKind.terminalRevoked]).
// Oddiy [Interceptor], navbatli emas: navbatli interceptor ichidan o'sha Dio bilan qayta so'rov yuborish uni qotirib
// qo'yadi. Parallel 401 larni muvofiqlashtirish [AuthSession.refresh] ning single-flight'ida.
class AuthInterceptor extends Interceptor {
  AuthInterceptor(this._dio, this._session);

  final Dio _dio;
  final AuthSession _session;

  static final RegExp _terminalPath = RegExp(
    r'/v1/(terminal(/|$)|devices/[^/]+/staff-session$)',
  );
  static final RegExp _openPath = RegExp(
    r'/v1/(devices/pair$|auth/(refresh|telegram|phone/(send-code|verify|select-account))$)',
  );

  static AuthScope _scopeOf(RequestOptions options) {
    final Object? explicit = options.extra[scopeExtraKey];
    if (explicit is AuthScope) return explicit;
    final String path = options.uri.path;
    if (_openPath.hasMatch(path)) return AuthScope.none;
    if (_terminalPath.hasMatch(path)) return AuthScope.terminal;
    return AuthScope.current;
  }

  @override
  void onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final AuthScope scope = _scopeOf(options);
    if (scope == AuthScope.none) return handler.next(options);

    String? token;
    _TokenKind? kind;
    if (scope == AuthScope.terminal) {
      token = _session.terminalToken;
      kind = _TokenKind.terminal;
    } else {
      // Xodim sessiyasi bo'lsa u; bo'lmasa shaxsiy ilova tokeni (kerak bo'lsa avval yangilanadi)
      if (_session.staff == null) await _session.refreshIfExpiring();
      token = _session.currentToken;
      kind = token == null
          ? null
          : (_session.staff?.token == token
                ? _TokenKind.staff
                : _TokenKind.user);
    }
    if (token != null && kind != null) {
      options.headers['Authorization'] = 'Bearer $token';
      options.extra[_sentTokenKey] = token;
      options.extra[_sentKindKey] = kind.name;
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    final RequestOptions options = err.requestOptions;
    final String? sentToken = options.extra[_sentTokenKey] as String?;
    final String? kindName = options.extra[_sentKindKey] as String?;
    if (err.response?.statusCode != 401 ||
        sentToken == null ||
        kindName == null) {
      return handler.next(err);
    }

    switch (_TokenKind.values.byName(kindName)) {
      case _TokenKind.staff:
        _session.handleStaffRejected(sentToken);
        return handler.next(err);
      case _TokenKind.terminal:
        await _session.handleTerminalRejected(sentToken);
        return handler.next(err);
      case _TokenKind.user:
        if (options.extra[_retriedKey] == true) return handler.next(err);
        try {
          // Boshqa so'rov tokenni allaqachon yangilagan bo'lsa (yuborilgan token endi joriy emas), qayta yuboramiz
          if (_session.user?.accessToken == sentToken) await _session.refresh();
          if (_session.user == null) return handler.next(err);
          handler.resolve(await _dio.fetch<Object?>(_retry(options)));
        } on ApiException {
          // Refresh o'ta olmadi (sessiya yopilgan yoki tarmoq yo'q): asl 401 xatosi ilovaga qaytadi
          handler.next(err);
        } on DioException catch (e) {
          handler.next(e);
        }
    }
  }

  RequestOptions _retry(RequestOptions options) {
    final Object? data = options.data;
    return options.copyWith(
      // FormData bir marta o'qiladi: qayta yuborish uchun nusxa kerak
      data: data is FormData ? data.clone() : data,
      extra: <String, Object?>{...options.extra, _retriedKey: true},
    );
  }
}
