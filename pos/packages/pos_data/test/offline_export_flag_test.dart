import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

/// Wave M5 S4-a: bootstrap `offline.export` gates the till's rescue file and the restore from its mirror copy.
void main() {
  test('offline.export is read; an older server that does not say it gets '
      'false', () {
    expect(
      PosOfflineSettings.of(<String, Object?>{
        'offline': <String, Object?>{'signing': true, 'export': true},
      }).export,
      isTrue,
    );
    expect(
      PosOfflineSettings.of(<String, Object?>{
        'offline': <String, Object?>{'signing': true},
      }).export,
      isFalse,
    );
    expect(PosOfflineSettings.of(const <String, Object?>{}).export, isFalse);
  });

  test('kept with the packaging rules', () {
    const PosOfflineSettings s = PosOfflineSettings(export: true);
    expect(s.withPackaging(const <PosPackagingRule>[]).export, isTrue);
  });
}
