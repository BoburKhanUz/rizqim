import 'package:dio/dio.dart';

/// `bool` so'rov parametrlarini `1`/`0` qilib yuboradi.
///
/// Dio `true`/`false` ni matn sifatida yozadi (`?include_revoked=false`), Laravelning `boolean` qoidasi esa faqat
/// `1`/`0` ni qabul qiladi va matnga 422 qaytaradi. Shu yerda bir marta tuzatiladi: yaratilgan va qo'lda yozilgan
/// barcha so'rovlar uchun.
class BoolQueryInterceptor extends Interceptor {
  const BoolQueryInterceptor();

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    if (options.queryParameters.isNotEmpty) {
      options.queryParameters = <String, Object?>{
        for (final MapEntry<String, dynamic> e
            in options.queryParameters.entries)
          e.key: _wire(e.value),
      };
    }
    handler.next(options);
  }

  static Object? _wire(Object? value) => switch (value) {
    final bool b => b ? 1 : 0,
    final Iterable<Object?> list => list.map(_wire).toList(),
    _ => value,
  };
}
