/// Rizqim backend klienti.
///
/// * qo'lda yozilgan kirish qatlami: terminalni ulash, xodim PIN sessiyasi, refresh token aylanishi (single-flight),
///   tokenlar uchun maxfiy ombor interfeysi;
/// * [RizqimClient]: sozlangan Dio + kirish holati + OpenAPI'dan yaratilgan klient (`client.api`);
/// * oshxona ekrani ([KitchenService], [KitchenTicket]) yaratilgan klient ustida;
/// * real-time: Reverb/Pusher klienti ([PusherClient]) va private kanal imzosi ([ChannelAuth]);
/// * M2: shartli GET'lar ([EtagStore], [ConditionalGetInterceptor]), o'qishlarni kuzatish ([ReadTracking]), kanal
///   reviziyasi ([ChannelRevision]), hodisa o'qishlarini taqsimlash ([PacedRefresh], [RefreshPacing],
///   [ScreenRelevance]), `GET /v1/realtime` ([RealtimeConfig]) va kanal holati ([LiveChannel]).
///
/// OpenAPI'dan yaratilgan klient sinflari va modellari (`OrderStatus`, `Product`, ...) bu yerdan chiqarilmaydi: ularning
/// nomlari `rizqim_core` bilan to'qnashadi. Kerak bo'lsa `import 'package:rizqim_api/generated.dart' as gen;`.
library;

export 'src/auth/api_exception.dart';
export 'src/auth/auth_interceptor.dart';
export 'src/auth/auth_remote.dart';
export 'src/auth/auth_session.dart';
export 'src/auth/models.dart';
export 'src/auth/pairing_code.dart';
export 'src/auth/pin_errors.dart';
export 'src/auth/token_storage.dart';
export 'src/bool_query_interceptor.dart';
export 'src/generated/rizqim_api.dart';
export 'src/kitchen/kitchen_models.dart';
export 'src/kitchen/kitchen_service.dart';
export 'src/kitchen/kitchen_setup.dart';
export 'src/realtime/backoff.dart';
export 'src/realtime/channel_auth.dart';
export 'src/realtime/io_socket.dart';
export 'src/realtime/live_channel.dart';
export 'src/realtime/pusher_client.dart';
export 'src/realtime/realtime_config.dart';
export 'src/realtime/realtime_socket.dart';
export 'src/rizqim_client.dart';
export 'src/sync/app_resume.dart';
export 'src/sync/channel_revision.dart';
export 'src/sync/conditional_get.dart';
export 'src/sync/read_tracking.dart';
export 'src/sync/refresh_pacing.dart';
export 'src/sync/screen_relevance.dart';
