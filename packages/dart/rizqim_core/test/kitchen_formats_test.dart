import 'package:rizqim_core/rizqim_core.dart';
import 'package:test/test.dart';

void main() {
  test(
    'formatDuration: minutes:seconds, hours when needed, never negative',
    () {
      expect(formatDuration(Duration.zero), '0:00');
      expect(formatDuration(const Duration(seconds: 9)), '0:09');
      expect(formatDuration(const Duration(minutes: 4, seconds: 5)), '4:05');
      expect(formatDuration(const Duration(minutes: 59, seconds: 59)), '59:59');
      expect(
        formatDuration(const Duration(hours: 1, minutes: 2, seconds: 3)),
        '1:02:03',
      );
      expect(formatDuration(const Duration(seconds: -30)), '0:00');
    },
  );

  test('OrderType short labels for kitchen tickets', () {
    expect(OrderType.dineIn.shortLabel, 'Zal');
    expect(OrderType.pickup.shortLabel, 'Olib ketish');
    expect(OrderType.delivery.shortLabel, 'Yetkazish');
  });

  test('OrderChannel labels', () {
    expect(OrderChannel.values.map((OrderChannel c) => c.wire), <String>[
      'telegram',
      'mobile',
      'pos_hall',
      'pos_takeaway',
      'aggregator',
    ]);
    expect(OrderChannel.fromWire('pos_takeaway')?.label, 'Kassa');
    expect(OrderChannel.fromWire('x'), isNull);
  });

  test('StaffRole labels', () {
    expect(StaffRole.labelOf('cook'), 'Oshpaz');
    expect(StaffRole.labelOf('manager'), 'Menejer');
    expect(StaffRole.labelOf('hostess'), 'Hostes');
    expect(StaffRole.labelOf('chef_de_partie'), 'chef_de_partie');
    expect(StaffRole.fromWire('waiter'), StaffRole.waiter);
  });
}
