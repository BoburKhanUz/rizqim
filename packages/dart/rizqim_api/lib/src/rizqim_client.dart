import 'package:dio/dio.dart';

import 'auth/auth_interceptor.dart';
import 'auth/auth_remote.dart';
import 'auth/auth_session.dart';
import 'auth/token_storage.dart';
import 'generated/rizqim_api.dart';

/// Ilova uchun tayyor to'plam: sozlangan [Dio] (token qo'yish va 401 da yangilash bilan), kirish holati ([auth]) va
/// OpenAPI'dan yaratilgan klient ([api]).
///
/// ```dart
/// final client = RizqimClient(baseUrl: 'https://api.example.uz/api', store: SecureStorageKeyValueStore());
/// await client.auth.restore();
/// await client.auth.pairTerminal('ABCD-EFGH');
/// final orders = await client.api.orders.orderIndex();
/// ```
///
/// [baseUrl] `/api` bilan tugaydi (yo'llar `/v1/...` dan boshlanadi).
class RizqimClient {
  factory RizqimClient({
    required String baseUrl,
    required SecureKeyValueStore store,
    Dio? dio,
    DateTime Function()? clock,
    Duration refreshSkew = const Duration(seconds: 30),
  }) {
    final Dio http = dio ?? Dio();
    http.options
      ..baseUrl = baseUrl
      ..connectTimeout = const Duration(seconds: 10)
      ..receiveTimeout = const Duration(seconds: 30)
      ..headers['Accept'] = 'application/json';
    final AuthSession auth = AuthSession(
      remote: AuthRemote(http),
      store: store,
      clock: clock,
      refreshSkew: refreshSkew,
    );
    http.interceptors.add(AuthInterceptor(http, auth));
    return RizqimClient._(http, auth);
  }

  RizqimClient._(this.dio, this.auth) : api = RizqimApi(dio);

  final Dio dio;
  final AuthSession auth;

  /// OpenAPI'dan yaratilgan klientlar (`api.terminal`, `api.orders`, ...).
  final RizqimApi api;
}
