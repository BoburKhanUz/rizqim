import 'package:dio/dio.dart';

import 'auth/auth_interceptor.dart';
import 'auth/auth_remote.dart';
import 'auth/auth_session.dart';
import 'auth/token_storage.dart';
import 'bool_query_interceptor.dart';
import 'generated/rizqim_api.dart';
import 'kitchen/kitchen_service.dart';
import 'realtime/channel_auth.dart';
import 'sync/conditional_get.dart';

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
///
/// [app] — POS ilovasi (`cashier`, `waiter`, `kds`, `customer_display`): har so'rovda [appHeader] sarlavhasida
/// yuboriladi (P6 review S4: server uni ilovasi yozilmagan eski terminalga yozib qo'yadi, kassadan boshqa ilovaga
/// lokal ko'prik markazi roli va kaliti berilmaydi).
///
/// [appVersion] — `<version>+<build>` (masalan `1.4.0+7`, ilovaning `AppConfig` qiymati): har so'rovda
/// [appVersionHeader] sarlavhasida yuboriladi (M5 S5: server uni `devices.app_version` ga yozadi). Berilmasa
/// sarlavha yuborilmaydi.
class RizqimClient {
  factory RizqimClient({
    required String baseUrl,
    required SecureKeyValueStore store,
    Dio? dio,
    DateTime Function()? clock,
    Duration refreshSkew = const Duration(seconds: 30),
    String? app,
    String? appVersion,
  }) {
    final Dio http = dio ?? Dio();
    http.options
      ..baseUrl = baseUrl
      ..connectTimeout = const Duration(seconds: 10)
      ..receiveTimeout = const Duration(seconds: 30)
      ..headers['Accept'] = 'application/json';
    if (app != null) http.options.headers[appHeader] = app;
    if (appVersion != null && appVersion.isNotEmpty) {
      http.options.headers[appVersionHeader] = appVersion;
    }
    final AuthSession auth = AuthSession(
      remote: AuthRemote(http),
      store: store,
      clock: clock,
      refreshSkew: refreshSkew,
    );
    final EtagStore etags = EtagStore();
    http.interceptors
      ..add(const BoolQueryInterceptor())
      ..add(AuthInterceptor(http, auth))
      // M2: after the token is set (the store key includes the actor)
      ..add(ConditionalGetInterceptor(etags, clock: clock));
    return RizqimClient._(http, auth, etags, clock);
  }

  RizqimClient._(this.dio, this.auth, this.etags, DateTime Function()? clock)
    : api = RizqimApi(dio),
      channelAuth = ChannelAuth(dio) {
    kitchen = KitchenService(api, dio, clock: clock);
    // Another person, another terminal, another session: nothing kept is theirs (the key holds the token anyway)
    auth.events.listen((AuthEvent e) {
      if (e.kind != AuthEventKind.tokensRefreshed) etags.clear();
    });
  }

  /// M2: the last `200` of each polled GET (`If-None-Match` / `304`), cleared on every sign-in change.
  final EtagStore etags;

  /// Ilova o'zini aytadigan sarlavha (backend `DeviceApp::HEADER`).
  static const String appHeader = 'X-Rizqim-App';

  /// Ilova versiyasi sarlavhasi (backend `X-Rizqim-App-Version`, `<version>+<build>`).
  static const String appVersionHeader = 'X-Rizqim-App-Version';

  final Dio dio;
  final AuthSession auth;

  /// OpenAPI'dan yaratilgan klientlar (`api.terminal`, `api.orders`, ...).
  final RizqimApi api;

  /// Oshxona ekrani: chiptalar, qatorni "tayyor" deb belgilash va qaytarish (xodim PIN sessiyasi bilan).
  late final KitchenService kitchen;

  /// Real-time private kanal imzosi (`POST /api/broadcasting/auth`).
  final ChannelAuth channelAuth;
}
