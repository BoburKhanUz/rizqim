import 'api_exception.dart';

/// PIN bilan kirish rad etilganda xodimga ko'rsatiladigan o'zbekcha matn va (bloklangan bo'lsa) qancha kutish.
///
/// docs/AUTH.md, 5-bo'lim: noto'g'ri PIN — 422 `pin_invalid` (kim borligi oshkor qilinmaydi); ko'p xato — 429
/// `pin_locked` va `Retry-After` (60 soniyadan boshlab ikki baravar oshadi); terminal yoki IP bo'yicha cheklov — 429
/// sababsiz.
class PinFailure {
  const PinFailure(this.message, {this.lockedFor});

  /// [ApiException] dan (PIN kirish so'rovi `POST /devices/{uuid}/staff-session`).
  factory PinFailure.from(ApiException e) {
    if (e.isNetworkError) {
      return const PinFailure(
        "Serverga ulanib bo'lmadi. Internet aloqasini tekshirib, qayta urinib ko'ring.",
      );
    }
    if (e.statusCode == 429) {
      final Duration wait = e.retryAfter ?? const Duration(seconds: 60);
      return PinFailure(
        e.reason == AuthReason.pinLocked
            ? "PIN ko'p marta noto'g'ri terildi. Kirish vaqtincha bloklandi."
            : "Juda ko'p urinish. Birozdan keyin qayta urinib ko'ring.",
        lockedFor: wait,
      );
    }
    if (e.reason == AuthReason.pinInvalid ||
        (e.statusCode == 422 && e.reason == null)) {
      return const PinFailure("PIN noto'g'ri. Qayta urinib ko'ring.");
    }
    if (e.statusCode == 401) {
      return const PinFailure(
        "Terminal do'kondan uzilgan. Uni qayta ulash kerak.",
      );
    }
    if (e.statusCode == 403) {
      return PinFailure(
        e.message.isNotEmpty && e.message != 'Ruxsat etilmagan'
            ? e.message
            : "Bu xodim shu terminalda ishlay olmaydi.",
      );
    }
    return PinFailure(e.message);
  }

  final String message;

  /// Bloklangan bo'lsa qancha kutish kerak (PIN klaviaturasi shu vaqtgacha o'chiriladi).
  final Duration? lockedFor;

  bool get isLocked => lockedFor != null;
}
