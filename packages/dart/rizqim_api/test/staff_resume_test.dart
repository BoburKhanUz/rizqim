import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

/// M5 S6-a: the record kept for "Davom etish" after an app restart (pos_data m5_s6_resume_test.dart has the flows).
void main() {
  final DateTime at = DateTime.utc(2026, 11, 21, 8);
  final StaffResumeRecord r = StaffResumeRecord(
    userId: 3,
    userName: 'Kamola',
    permissions: <String>{'pos.take_payment', 'orders.manage'},
    signedInAt: at,
  );

  test(
    'the record holds only the id, the name, the permissions and the time',
    () {
      final StaffResumeRecord back = StaffResumeRecord.tryParse(r.encode())!;
      expect(back.userId, 3);
      expect(back.userName, 'Kamola');
      expect(back.permissions, <String>{'orders.manage', 'pos.take_payment'});
      expect(back.signedInAt, at);
      expect(
        r.encode(),
        '{"v":1,"staff_id":3,"name":"Kamola","permissions":["orders.manage","pos.take_payment"],'
        '"signed_in_at":"2026-11-21T08:00:00.000Z"}',
      );
    },
  );

  test('usable from the PIN login to 12 h after it, never before it', () {
    expect(r.usableAt(at), isTrue);
    expect(r.usableAt(at.add(const Duration(hours: 11, minutes: 59))), isTrue);
    expect(r.usableAt(at.add(const Duration(hours: 12))), isTrue);
    expect(r.usableAt(at.add(const Duration(hours: 12, minutes: 1))), isFalse);
    expect(r.usableAt(at.subtract(const Duration(seconds: 1))), isFalse);
  });

  test('a damaged, foreign or other-version value is never trusted', () {
    for (final String raw in <String>[
      '',
      'null',
      '[]',
      '{"v":2,"staff_id":3,"name":"K","permissions":[],"signed_in_at":"2026-11-21T08:00:00.000Z"}',
      '{"v":1,"staff_id":"3","name":"K","permissions":[],"signed_in_at":"2026-11-21T08:00:00.000Z"}',
      '{"v":1,"staff_id":3,"name":"K","permissions":[],"signed_in_at":"yesterday"}',
      '{"v":1,"staff_id":3,"name":"K","signed_in_at":"2026-11-21T08:00:00.000Z"}',
    ]) {
      expect(StaffResumeRecord.tryParse(raw), isNull, reason: raw);
    }
  });
}
