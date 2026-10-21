import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_kds/src/board/wait_level.dart';
import 'package:rizqim_kds/src/settings/kds_settings.dart';

void main() {
  test('defaults: dark, sound on, 10/20 min thresholds, 15 min idle', () {
    const KdsSettings s = KdsSettings();
    expect(s.dark, isTrue);
    expect(s.muted, isFalse);
    expect(s.warnAfter, const Duration(minutes: 10));
    expect(s.lateAfter, const Duration(minutes: 20));
    expect(s.idleTimeout, const Duration(minutes: 15));
  });

  test('WaitLevel thresholds', () {
    const KdsSettings s = KdsSettings();
    expect(
      WaitLevel.of(const Duration(minutes: 9, seconds: 59), s),
      WaitLevel.normal,
    );
    expect(WaitLevel.of(const Duration(minutes: 10), s), WaitLevel.warn);
    expect(
      WaitLevel.of(const Duration(minutes: 19, seconds: 59), s),
      WaitLevel.warn,
    );
    expect(WaitLevel.of(const Duration(minutes: 20), s), WaitLevel.late);
    final KdsSettings tight = s.copyWith(
      warnAfter: const Duration(minutes: 5),
      lateAfter: const Duration(minutes: 8),
    );
    expect(WaitLevel.of(const Duration(minutes: 6), tight), WaitLevel.warn);
    expect(WaitLevel.of(const Duration(minutes: 8), tight), WaitLevel.late);
  });

  test('"late" always comes after "hurry"', () {
    final KdsSettings s = const KdsSettings().copyWith(
      warnAfter: const Duration(minutes: 25),
    );
    expect(s.lateAfter, const Duration(minutes: 30));
  });

  test(
    'round trip through the store; bad values fall back to defaults',
    () async {
      final MemorySettingsStore store = MemorySettingsStore();
      final KdsSettingsController c = KdsSettingsController(store);
      c.update(
        const KdsSettings().copyWith(
          idleTimeout: () => null,
          muted: true,
          dark: false,
          fontScale: 1.4,
          warnAfter: const Duration(minutes: 8),
          lateAfter: const Duration(minutes: 15),
        ),
      );
      await Future<void>.delayed(Duration.zero);
      expect(store.values['idle_minutes'], 0);

      final KdsSettingsController again = KdsSettingsController(store);
      await again.load();
      expect(again.value, c.value);

      final KdsSettingsController broken = KdsSettingsController(
        MemorySettingsStore(<String, Object?>{
          'warn_minutes': 'x',
          'late_minutes': -3,
          'font_scale': 9.0,
          'muted': 'yes',
        }),
      );
      await broken.load();
      expect(broken.value, const KdsSettings());
    },
  );
}
