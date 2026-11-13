import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6-44: korxona hisobi — xodimni talon kodi yoki telefon bilan topish (qolgan limitlar), hisobning korxona maydonlari
/// va `account` tenderida xodim kodi.
void main() {
  late Harness h;

  setUp(() async {
    h = Harness();
    await h.signIn();
  });

  const Map<String, Object?> lookup = <String, Object?>{
    'success': true,
    'message': '',
    'data': <String, Object?>{
      'account': <String, Object?>{
        'uuid': 'c1',
        'name': 'Navoiy zavodi',
        'company_name': '"Navoiy Metall" MChJ',
        'talon_mode': 'daily_limit',
        'charge_approval': false,
        'is_blocked': false,
        'credit_limit': null,
        'balance': '45000.00',
      },
      'member': <String, Object?>{
        'uuid': 'm1',
        'name': 'Aziz Rahimov',
        'phone': '+998 90 *** ** 33',
        'code': '5550001111',
        'daily_limit': '60000.00',
        'meals_per_day': 2,
        'is_active': true,
      },
      'usage': <String, Object?>{
        'business_date': '2026-11-20',
        'member': <String, Object?>{
          'amount': '45000.00',
          'meals': 1,
          'daily_limit': '60000.00',
          'daily_available': '15000.00',
          'meals_per_day': 2,
          'meals_left': 1,
        },
        'month': <String, Object?>{
          'amount': '45000.00',
          'monthly_limit': '1000000.00',
          'available': '955000.00',
        },
        'credit_available': null,
      },
    },
  };

  test('a member is found by the talon code with what is left today', () async {
    h.adapter.respond = (RequestOptions o) => const FakeResponse(200, lookup);
    final PosCorporateLookup found = await h.pos.accounts.lookupMember(
      code: '5550001111',
    );
    expect(h.last.uri.path, '/api/v1/pos/customer-accounts/members/lookup');
    expect(h.last.uri.queryParameters, <String, String>{'code': '5550001111'});
    expect(found.accountUuid, 'c1');
    expect(found.memberName, 'Aziz Rahimov');
    expect(found.memberPhone, '+998 90 *** ** 33');
    expect(found.chargeApproval, isFalse);
    expect(found.businessDate, '2026-11-20');
    expect(found.dailyAvailable, const PosMoney.som(15000));
    expect(found.mealsLeft, 1);
    expect(found.monthAvailable, const PosMoney.som(955000));
    expect(found.creditAvailable, isNull);
    expect(found.allows(const PosMoney.som(15000)), isTrue);
    expect(found.allows(const PosMoney.som(15001)), isFalse);
  });

  test('by phone; an unknown talon is the server refusal', () async {
    h.adapter.respond = (RequestOptions o) =>
        const FakeResponse(404, <String, Object?>{
          'success': false,
          'message': 'Xodim topilmadi: talon kodi yoki telefonni tekshiring.',
          'data': <String, Object?>{'reason': 'member_not_found'},
        });
    await expectLater(
      h.pos.accounts.lookupMember(phone: '901112233'),
      throwsA(isA<Object>()),
    );
    expect(h.last.uri.queryParameters, <String, String>{'phone': '901112233'});
  });

  test('the account carries its kind and the tender the member', () {
    final PosCustomerAccount account = PosCustomerAccount.fromJson(
      const PosJson(<String, Object?>{
        'uuid': 'c1',
        'name': 'Navoiy zavodi',
        'balance': '0.00',
        'is_blocked': false,
        'kind': 'corporate',
        'company_name': 'Navoiy Metall',
        'monthly_limit': '1000000.00',
        'charge_approval': false,
        'talon_mode': 'subscription',
        'members_count': 12,
      }),
    );
    expect(account.isCorporate, isTrue);
    expect(account.chargeApproval, isFalse);
    expect(account.talonMode, 'subscription');
    expect(account.membersCount, 12);
    expect(account.monthlyLimit, const PosMoney.som(1000000));
    // A nasiya account of an older server: the defaults
    final PosCustomerAccount nasiya = PosCustomerAccount.fromJson(
      const PosJson(<String, Object?>{
        'uuid': 'a1',
        'name': 'Karim',
        'balance': '0.00',
      }),
    );
    expect(nasiya.isCorporate, isFalse);
    expect(nasiya.chargeApproval, isTrue);

    expect(
      const PosTenderInput.account(
        PosMoney.som(45000),
        accountUuid: 'c1',
        memberCode: '5550001111',
      ).toJson(),
      <String, Object?>{
        'tender': 'account',
        'amount': '45000.00',
        'account_uuid': 'c1',
        'member_code': '5550001111',
      },
    );
    expect(
      const PosTenderInput.account(
        PosMoney.som(1000),
        accountUuid: 'a1',
      ).toJson(),
      <String, Object?>{
        'tender': 'account',
        'amount': '1000.00',
        'account_uuid': 'a1',
      },
    );
  });

  test(
    'stage B: an account\'s members for the talons (active first)',
    () async {
      h.adapter.respond = (RequestOptions o) =>
          const FakeResponse(200, <String, Object?>{
            'success': true,
            'message': '',
            'data': <String, Object?>{
              'members': <Object?>[
                <String, Object?>{
                  'uuid': 'm1',
                  'name': 'Aziz Rahimov',
                  'phone': '+998901112233',
                  'code': '5550001111',
                  'daily_limit': '60000.00',
                  'meals_per_day': null,
                  'is_active': true,
                  'note': null,
                },
                <String, Object?>{
                  'uuid': 'm2',
                  'name': 'Bobur',
                  'phone': null,
                  'code': 'NAV-0002',
                  'daily_limit': null,
                  'meals_per_day': 1,
                  'is_active': false,
                  'note': 'ishdan ketgan',
                },
              ],
              'active_count': 1,
            },
          });
      final List<PosAccountMember> list = await h.pos.accounts.members(
        'c1',
        active: true,
      );
      expect(h.last.uri.path, '/api/v1/pos/customer-accounts/c1/members');
      expect(h.last.uri.queryParameters, <String, String>{'active': '1'});
      expect(list.map((PosAccountMember m) => m.code), <String>[
        '5550001111',
        'NAV-0002',
      ]);
      expect(list.first.dailyLimit, const PosMoney.som(60000));
      expect(list.last.active, isFalse);
      expect(list.last.mealsPerDay, 1);
    },
  );

  test('stage B fake: lookup, member charge rules and members list', () async {
    final FakeCashierBackend backend = FakeCashierBackend(h.adapter.state);
    final String account = backend.seedCorporate(
      name: 'Paxta Tekstil',
      monthlyLimit: '100000.00',
    );
    backend.seedMember(
      account,
      name: 'Aziz',
      code: 'PT-1',
      phone: '+998901112233',
      dailyLimit: '40000.00',
    );
    final PosCorporateLookup byPhone = await h.pos.accounts.lookupMember(
      phone: '90 111 22 33',
    );
    expect(byPhone.code, 'PT-1');
    expect(byPhone.dailyAvailable, const PosMoney.som(40000));
    expect(byPhone.memberPhone, '+99890 *** ** 33');
    expect(byPhone.chargeApproval, isFalse);
    await expectLater(
      h.pos.accounts.lookupMember(code: 'NOPE'),
      throwsA(
        isA<PosException>().having(
          (PosException e) => e.reason,
          'reason',
          'member_not_found',
        ),
      ),
    );
  });
}
