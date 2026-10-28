/// P4-21: `entitlements` bloki, real backend (`Entitlements::toArray`) shaklida, testlar va soxta server uchun.
///
/// [plan]: `start` (bron, bo'nak, nasiya, KDS, xizmat haqi yo'q), `asosiy` (shular bor), `pro` (hammasi). [status]:
/// `trialing`, `active`, `past_due`, `locked`. [notice] — to'lanmagan hisob-faktura xabari ([fakeBillingNotice]).
Map<String, Object?> fakeEntitlements({
  String plan = 'asosiy',
  String status = 'active',
  bool legacy = false,
  DateTime? trialEndsAt,
  DateTime? lockAt,
  Map<String, Object?>? notice,
  int activePos = 1,
  int activeKds = 1,
  bool canAddKds = true,
}) {
  const List<String> gated = <String>[
    'bookings',
    'deposits',
    'customer_accounts',
    'kds',
    'service_charge',
    'multi_branch_reports',
    'aggregator_orders',
    'banquet',
  ];
  const Map<String, List<String>> byPlan = <String, List<String>>{
    'start': <String>[],
    'asosiy': <String>[
      'bookings',
      'deposits',
      'customer_accounts',
      'kds',
      'service_charge',
    ],
    'pro': gated,
  };
  const Map<String, String> names = <String, String>{
    'start': 'Start',
    'asosiy': 'Asosiy',
    'pro': 'Pro',
  };
  final List<String> has = byPlan[plan] ?? gated;
  final bool locked = status == 'locked';
  return <String, Object?>{
    'plan': <String, Object?>{
      'code': plan,
      'name': names[plan] ?? plan,
      'tier': plan,
      'version': 2,
    },
    'legacy': legacy,
    'period': 'monthly',
    'status': status,
    'trial_ends_at': trialEndsAt?.toIso8601String(),
    'current_period': <String, Object?>{
      'start': '2026-10-01T00:00:00+05:00',
      'end': '2026-11-01T00:00:00+05:00',
    },
    'term_ends_at': null,
    'locked_since': locked ? lockAt?.toIso8601String() : null,
    'lock_at': lockAt?.toIso8601String(),
    'settings_locked': locked,
    'features': <String, Object?>{
      'hall': true,
      'room_fee': true,
      'guest_items': true,
      'shifts': true,
      'rizqim_orders': true,
      for (final String f in gated) f: has.contains(f),
    },
    'required_plans': <String, Object?>{
      'bookings': 'asosiy',
      'deposits': 'asosiy',
      'customer_accounts': 'asosiy',
      'kds': 'asosiy',
      'service_charge': 'asosiy',
      'multi_branch_reports': 'pro',
      'aggregator_orders': 'pro',
      'banquet': 'pro',
    },
    'devices': <String, Object?>{
      'pos': <String, Object?>{
        'included': plan == 'pro' ? 2 : 1,
        'active': activePos,
        'extra': activePos > (plan == 'pro' ? 2 : 1)
            ? activePos - (plan == 'pro' ? 2 : 1)
            : 0,
        'extra_price': '59000.00',
        'can_add': !(locked && activePos >= (plan == 'pro' ? 2 : 1)),
      },
      'kds': <String, Object?>{
        'included': plan == 'start' ? 0 : 1,
        'active': activeKds,
        'extra': 0,
        'extra_price': '29000.00',
        'can_add': has.contains('kds') && canAddKds,
      },
      'waiter': <String, Object?>{
        'included': null,
        'unlimited': true,
        'extra_price': '0.00',
        'can_add': true,
      },
    },
    'notice': notice,
  };
}

/// `entitlements.notice`: [locked] — sozlamalar yopilgan; aks holda eslatma (muddati [daysOverdue] kun o'tgan).
Map<String, Object?> fakeBillingNotice({
  bool locked = false,
  int daysOverdue = 3,
  DateTime? lockAt,
  String number = 'OB-202610-000042',
  String outstanding = '249000.00',
}) => <String, Object?>{
  'level': locked ? 'locked' : 'reminder',
  'message': locked
      ? "$number hisob-faktura (249 000 so'm) to'lanmagan: yangi sozlamalar va tarifdagidan ortiq imkoniyatlar yopildi. To'lov qilinishi bilan darhol ochiladi. Kassa ishlashda davom etadi."
      : "$number hisob-faktura (249 000 so'm) muddati $daysOverdue kun o'tdi. Yangi sozlamalar yopilishidan oldin to'lang. Kassa ishlashda davom etadi.",
  'days_overdue': daysOverdue,
  'lock_at': lockAt?.toIso8601String(),
  'invoice': <String, Object?>{
    'uuid': 'inv-1',
    'number': number,
    'amount': '249000.00',
    'outstanding': outstanding,
    'due_date': '2026-11-11',
  },
};
