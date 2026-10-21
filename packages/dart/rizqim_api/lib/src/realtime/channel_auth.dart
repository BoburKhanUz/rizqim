import 'package:dio/dio.dart';

import '../auth/api_exception.dart';
import '../auth/auth_remote.dart';

/// Private kanal imzosi: `POST /api/broadcasting/auth` (form: `socket_id`, `channel_name`) → `{auth}`.
///
/// Terminal (KDS, kassa) **o'z tokeni** bilan imzolaydi ([AuthScope.terminal]): ekranda hech kim PIN bilan kirmagan
/// bo'lsa ham real-time ishlaydi. Terminal faqat `private-pos-branch.{branchId}` ga qo'shila oladi (boshqasiga 403).
/// Terminal tokeni rad etilsa (401) [AuthInterceptor] terminalni "o'chirilgan" deb belgilaydi.
class ChannelAuth {
  ChannelAuth(this._dio);

  final Dio _dio;

  Future<String> terminal(String socketId, String channelName) =>
      _authorize(socketId, channelName, AuthScope.terminal);

  /// Xodim sessiyasi yoki shaxsiy ilova tokeni bilan.
  Future<String> current(String socketId, String channelName) =>
      _authorize(socketId, channelName, AuthScope.current);

  Future<String> _authorize(
    String socketId,
    String channelName,
    AuthScope scope,
  ) async {
    final Response<Object?> response;
    try {
      response = await _dio.post<Object?>(
        '/broadcasting/auth',
        data: <String, String>{
          'socket_id': socketId,
          'channel_name': channelName,
        },
        options: scoped(
          scope,
          Options(contentType: Headers.formUrlEncodedContentType),
        ),
      );
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
    final Object? body = response.data;
    final Object? auth = body is Map ? body['auth'] : null;
    if (auth is! String || auth.isEmpty) {
      throw ApiException(
        message: 'Real-time imzosi kelmadi.',
        statusCode: response.statusCode,
      );
    }
    return auth;
  }
}
