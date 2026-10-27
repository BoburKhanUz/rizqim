/// Rizqim backend klienti.
///
/// * qo'lda yozilgan kirish qatlami: terminalni ulash, xodim PIN sessiyasi, refresh token aylanishi (single-flight),
///   tokenlar uchun maxfiy ombor interfeysi;
/// * [RizqimClient]: sozlangan Dio + kirish holati + OpenAPI'dan yaratilgan klient (`client.api`);
/// * oshxona ekrani ([KitchenService], [KitchenTicket]) yaratilgan klient ustida;
/// * real-time: Reverb/Pusher klienti ([PusherClient]) va private kanal imzosi ([ChannelAuth]).
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
export 'src/realtime/pusher_client.dart';
export 'src/realtime/realtime_socket.dart';
export 'src/rizqim_client.dart';
