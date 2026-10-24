/// Testlar va backend'siz sinash uchun: soxta POS backend ([FakePosState]), uning Dio adapteri ([FakePosAdapter]),
/// soxta Reverb ([FakePosRealtimeHub]) va Linux'da ilovani ishga tushirish uchun haqiqiy HTTP/WebSocket server
/// ([FakePosServer]). Kassa (P3-21, P3-22) va ofitsiant (P3-25) testlari ham shularni ishlatadi.
library;

export 'src/testing/fake_cashier_backend.dart';
export 'src/testing/fake_pos_adapter.dart';
export 'src/testing/fake_pos_server.dart';
export 'src/testing/fake_pos_state.dart';
export 'src/testing/fake_realtime.dart';
export 'src/testing/fake_sync_backend.dart';
