import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

void main() {
  test('a wrong PIN', () {
    final PinFailure f = PinFailure.from(
      const ApiException(message: 'x', statusCode: 422, reason: 'pin_invalid'),
    );
    expect(f.message, "PIN noto'g'ri. Qayta urinib ko'ring.");
    expect(f.isLocked, isFalse);
  });

  test('locked: the wait comes from Retry-After / retry_after', () {
    final PinFailure f = PinFailure.from(
      const ApiException(
        message: 'x',
        statusCode: 429,
        reason: 'pin_locked',
        retryAfter: Duration(seconds: 120),
      ),
    );
    expect(f.message, contains('bloklandi'));
    expect(f.lockedFor, const Duration(seconds: 120));
  });

  test(
    'a plain rate limit also waits, a minute when the server does not say',
    () {
      final PinFailure f = PinFailure.from(
        const ApiException(message: 'x', statusCode: 429),
      );
      expect(f.message, contains("Juda ko'p urinish"));
      expect(f.lockedFor, const Duration(seconds: 60));
    },
  );

  test('network, revoked terminal and other refusals', () {
    expect(
      PinFailure.from(const ApiException(message: 'x')).message,
      contains("Serverga ulanib bo'lmadi"),
    );
    expect(
      PinFailure.from(const ApiException(message: 'x', statusCode: 401))
          .message,
      contains('qayta ulash'),
    );
    expect(
      PinFailure.from(
        const ApiException(message: 'Ruxsat etilmagan', statusCode: 403),
      ).message,
      "Bu xodim shu terminalda ishlay olmaydi.",
    );
    expect(
      PinFailure.from(
        const ApiException(message: 'Boshqa xato', statusCode: 500),
      ).message,
      'Boshqa xato',
    );
  });
}
