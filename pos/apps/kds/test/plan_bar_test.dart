import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_kds/src/billing/kds_plan.dart';
import 'package:rizqim_kds/src/screens/pairing_screen.dart';

import 'support/fake_kds.dart';
import 'support/fake_kitchen_state.dart';
import 'support/helpers.dart';

/// P4-21: the kitchen display reads the plan (GET /owner/billing/entitlements; the same as `entitlements` of the
/// bootstrap). The board never stops for it: tickets, "ready" and recall do not depend on the plan or the debt. An unpaid invoice
/// and "KDS is in the Asosiy plan" show as a plain bar.
Map<String, Object?> plan({
  bool kds = true,
  String? notice,
  int daysToLock = 4,
  DateTime? now,
}) {
  final DateTime lockAt = (now ?? DateTime(2026, 10, 21, 12)).add(
    Duration(days: daysToLock),
  );
  return <String, Object?>{
    'plan': <String, Object?>{
      'code': kds ? 'asosiy' : 'start',
      'name': kds ? 'Asosiy' : 'Start',
    },
    'legacy': false,
    'features': <String, Object?>{'kds': kds},
    'required_plans': <String, Object?>{'kds': 'asosiy'},
    'notice': notice == null
        ? null
        : <String, Object?>{
            'level': notice,
            'message': 'x',
            'days_overdue': 3,
            'lock_at': lockAt.toIso8601String(),
            'invoice': <String, Object?>{
              'number': 'OB-202610-000042',
              'outstanding': '249000.00',
            },
          },
  };
}

void main() {
  group('KdsPlan', () {
    test('reads the plan: KDS in or out, the plan that has it, the notice', () {
      final KdsPlan start = KdsPlan.fromJson(plan(kds: false));
      expect(start.kdsIncluded, isFalse);
      expect(start.kdsPlanName, 'Asosiy');
      expect(start.kdsLockLabel, 'Oshxona ekrani (KDS) Asosiy tarifda bor');
      expect(start.notice, isNull);
      expect(KdsPlan.fromJson(plan()).kdsIncluded, isTrue);
      // A legacy store has everything whatever the list says
      expect(
        KdsPlan.fromJson(<String, Object?>{
          'legacy': true,
          'features': <String, Object?>{'kds': false},
        }).kdsIncluded,
        isTrue,
      );
      expect(KdsPlan.fromJson('nonsense').kdsIncluded, isTrue);
      expect(KdsPlan.fromJson(null).notice, isNull);
    });

    test('the notice speaks plain words', () {
      final DateTime now = DateTime(2026, 10, 21, 12);
      final KdsPlanNotice four = KdsPlan.fromJson(
        plan(notice: 'reminder', now: now),
      ).notice!;
      expect(four.daysUntilLock(now), 4);
      expect(
        four.title(now),
        "To'lov muddati o'tdi — 4 kundan keyin sozlamalar yopiladi",
      );
      expect(four.text, contains('Oshxona ekrani ishlashda davom etadi'));
      final KdsPlanNotice locked = KdsPlan.fromJson(
        plan(notice: 'locked', now: now),
      ).notice!;
      expect(locked.title(now), "Sozlamalar yopiq — to'lov qiling");
      expect(locked.daysUntilLock(now), isNull);
    });
  });

  group('the bar on the board', () {
    testWidgets('nothing for a store that has paid and has KDS in its plan', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = KdsHarness(realtime: false);
      h.state.entitlements = plan();
      h.state.addOrder();
      await pumpBoard(tester, h);
      expect(find.textContaining("To'lov muddati"), findsNothing);
      expect(find.textContaining('tarifda bor'), findsNothing);
      expect(find.text('1 ta chipta'), findsOneWidget);
    });

    testWidgets(
      'an overdue invoice: days to the lock, and the board keeps working',
      (WidgetTester tester) async {
        final KdsHarness h = KdsHarness(realtime: false);
        h.state.entitlements = plan(notice: 'reminder');
        final String uuid = h.state.addOrder();
        await pumpBoard(tester, h);
        expect(
          find.text(
            "To'lov muddati o'tdi — 4 kundan keyin sozlamalar yopiladi",
          ),
          findsOneWidget,
        );
        expect(
          find.textContaining('Oshxona ekrani ishlashda davom etadi'),
          findsOneWidget,
        );
        // "Ready" still works
        await tester.tap(buttonLabeled('Hammasi tayyor'));
        await tester.pumpAndSettle();
        expect(
          h.state.orders.firstWhere(
            (Map<String, Object?> o) => o['uuid'] == uuid,
          )['status'],
          isNot('accepted'),
        );
      },
    );

    testWidgets('locked: "Sozlamalar yopiq", the tickets are still there', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = KdsHarness(realtime: false);
      h.state.entitlements = plan(notice: 'locked');
      h.state.addOrder();
      await pumpBoard(tester, h);
      expect(find.text("Sozlamalar yopiq — to'lov qiling"), findsOneWidget);
      expect(find.text('1 ta chipta'), findsOneWidget);
    });

    testWidgets(
      'KDS not in the plan: it says which plan has it and keeps working',
      (WidgetTester tester) async {
        final KdsHarness h = KdsHarness(realtime: false);
        h.state.entitlements = plan(kds: false);
        h.state.addOrder();
        await pumpBoard(tester, h);
        expect(
          find.text('Oshxona ekrani (KDS) Asosiy tarifda bor'),
          findsOneWidget,
        );
        expect(
          find.textContaining('Ekran ishlashda davom etadi'),
          findsOneWidget,
        );
        expect(find.text('1 ta chipta'), findsOneWidget);
      },
    );

    testWidgets('an old server without the endpoint shows no bar', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = KdsHarness(realtime: false);
      h.state.addOrder();
      await pumpBoard(tester, h);
      expect(find.textContaining("To'lov muddati"), findsNothing);
      expect(find.textContaining('tarifda bor'), findsNothing);
      expect(find.text('1 ta chipta'), findsOneWidget);
    });

    testWidgets('the plan is read again: paying removes the bar', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = KdsHarness(realtime: false);
      h.state.entitlements = plan(notice: 'locked');
      await pumpBoard(tester, h);
      expect(find.text("Sozlamalar yopiq — to'lov qiling"), findsOneWidget);
      h.state.entitlements = plan();
      await settle(tester, h.services.plan.refresh());
      await tester.pumpAndSettle();
      expect(find.text("Sozlamalar yopiq — to'lov qiling"), findsNothing);
    });
  });

  group('pairing', () {
    testWidgets(
      'a KDS that the plan does not allow is explained in plain words',
      (WidgetTester tester) async {
        final KdsHarness h = KdsHarness(realtime: false);
        h.backend.respond = (RequestOptions o) =>
            const FakeResponse(403, <String, Object?>{
              'success': false,
              'message':
                  'Bu imkoniyat (Oshxona ekrani (KDS)) Asosiy tarifida bor.',
              'code': 'plan_feature_missing',
              'data': <String, Object?>{
                'reason': 'plan_feature_missing',
                'feature': 'kds',
                'required_plan': 'asosiy',
              },
            });
        await pumpKds(tester, h);
        await tester.enterText(find.byType(TextField), 'ABCDEFGH');
        await tester.pump();
        await tester.tap(buttonLabeled('Ulash'));
        await tester.pumpAndSettle();
        expect(
          find.textContaining(
            'KDS) hozirgi tarifda yo\'q: u Asosiy tarifda bor',
          ),
          findsOneWidget,
        );
        expect(
          find.textContaining(
            'kassa va boshqa qurilmalar ishlashda davom etadi',
          ),
          findsOneWidget,
        );
        expect(find.text('Terminalni ulash'), findsOneWidget);
      },
    );

    testWidgets('the device limit of a locked subscription says what to do', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = KdsHarness(realtime: false);
      h.backend.respond = (RequestOptions o) =>
          const FakeResponse(403, <String, Object?>{
            'success': false,
            'message': 'x',
            'data': <String, Object?>{
              'reason': 'device_limit',
              'device_type': 'kds',
            },
          });
      await pumpKds(tester, h);
      await tester.enterText(find.byType(TextField), 'ABCDEFGH');
      await tester.pump();
      await tester.tap(buttonLabeled('Ulash'));
      await tester.pumpAndSettle();
      expect(
        find.textContaining("tarifdagidan ortiq qurilma ulab bo'lmaydi"),
        findsOneWidget,
      );
    });

    test('planRefusalText leaves other refusals alone', () {
      expect(
        planRefusalText(
          const ApiException(
            message: 'Kod noto\'g\'ri',
            statusCode: 422,
            reason: 'pairing_invalid',
          ),
        ),
        isNull,
      );
      expect(
        planRefusalText(
          const ApiException(
            message: 'x',
            statusCode: 403,
            reason: 'subscription_locked',
          ),
        ),
        contains('yopilgan'),
      );
    });
  });
}
