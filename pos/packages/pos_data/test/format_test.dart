import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6-11: filial formati (bootstrap'dagi `format`): profil, rejim, birinchi ekran, samarali modullar, eski server va
/// 403 `module_disabled`.
void main() {
  Map<String, Object?> format({
    String profile = 'osh_markazi',
    String mode = 'counter',
    String firstScreen = 'quick_counter',
    List<String> modules = const <String>[
      'quick_counter',
      'queue_numbers',
      'batches',
      'terminal_card_flow',
    ],
    bool serviceChargeZero = false,
  }) => <String, Object?>{
    'profile': profile,
    'profile_label': 'Osh markazi',
    'mode': mode,
    'first_screen': firstScreen,
    'modules': modules,
    'locks': <String, Object?>{'service_charge_zero': serviceChargeZero},
  };

  group('PosFormat', () {
    test('reads the profile, mode, first screen, modules and locks; unknown modules are kept aside', () {
      final PosFormat f = PosFormat.fromJson(
        PosJson(
          format(
            modules: <String>['quick_counter', 'queue_numbers', 'teleport'],
            serviceChargeZero: true,
          ),
        ),
      );
      expect(f.profile, 'osh_markazi');
      expect(f.profileLabel, 'Osh markazi');
      expect(f.mode, PosMode.counter);
      expect(f.firstScreen, PosFirstScreen.quickCounter);
      expect(f.has(PosModule.queueNumbers), isTrue);
      expect(f.has(PosModule.rooms), isFalse);
      expect(
        f.modules.hasAll(<PosModule>[
          PosModule.quickCounter,
          PosModule.queueNumbers,
        ]),
        isTrue,
      );
      expect(
        f.modules.hasAny(<PosModule>[PosModule.kds, PosModule.rooms]),
        isFalse,
      );
      expect(f.serviceChargeZero, isTrue);
      expect(f.unknownModules, <String>['teleport']);
    });

    test('every module key matches the server', () {
      expect(PosModule.values.map((PosModule m) => m.wire).toList(), <String>[
        'quick_counter',
        'phone_mode',
        'queue_numbers',
        'queue_calling',
        'customer_display',
        'daily_menu',
        'combo_meal',
        'batches',
        'batch_writeoff',
        'terminal_card_flow',
        'rooms',
        'room_fee',
        'guest_items',
        'bookings',
        'customer_accounts',
        'service_charge',
        'kds',
      ]);
      expect(PosModule.fromWire('kds'), PosModule.kds);
      expect(PosModule.fromWire('nope'), isNull);
      expect(
        PosFirstScreen.fromWire('booking_calendar'),
        PosFirstScreen.bookingCalendar,
      );
      expect(PosFirstScreen.fromWire(null), PosFirstScreen.hallMap);
    });
  });

  group('the bootstrap', () {
    late Harness h;

    setUp(() async {
      h = Harness();
      FakeCashierBackend(h.state);
      await h.signIn(userId: 2); // the manager: opens nasiya accounts alone
    });

    test(
      'an old server has no format: the till is the phase 3 choyxona',
      () async {
        final PosBootstrap b = await h.pos.session.loadBootstrap();
        expect(b.format.profile, 'choyxona');
        expect(b.format.firstScreen, PosFirstScreen.hallMap);
        expect(b.modules.has(PosModule.rooms), isTrue);
        expect(b.modules.has(PosModule.guestItems), isTrue);
        expect(b.modules.has(PosModule.queueNumbers), isFalse);
        expect(b.modules.has(PosModule.dailyMenu), isFalse);
      },
    );

    test('the format block is read; one the app cannot read falls back to the choyxona', () async {
      h.state.format = format();
      final PosBootstrap b = await h.pos.session.loadBootstrap();
      expect(b.format.profile, 'osh_markazi');
      expect(b.format.mode, PosMode.counter);
      expect(b.modules.has(PosModule.batches), isTrue);
      expect(b.modules.has(PosModule.rooms), isFalse);

      h.state.format = <String, Object?>{'modules': 5};
      final PosBootstrap bad = await h.pos.session.loadBootstrap();
      expect(bad.format.profile, 'choyxona');
      expect(bad.modules.has(PosModule.rooms), isTrue);
    });

    test('a switched off module is a PosModuleDisabled naming it', () async {
      h.state.format = format();
      await expectLater(
        h.pos.accounts.create(const PosAccountInput(name: 'Karim')),
        throwsA(
          isA<PosModuleDisabled>()
              .having(
                (PosModuleDisabled e) => e.reason,
                'reason',
                'module_disabled',
              )
              .having(
                (PosModuleDisabled e) => e.module,
                'module',
                'customer_accounts',
              )
              .having((PosModuleDisabled e) => e.statusCode, 'status', 403),
        ),
      );
      h.state.format = format(modules: <String>['customer_accounts']);
      await h.pos.accounts.create(const PosAccountInput(name: 'Karim'));
    });
  });
}
