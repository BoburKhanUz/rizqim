import 'package:flutter_test/flutter_test.dart';

import 'support/harness.dart';

/// Wave M2 on the till: what the top bar says when the socket is subscribed but the server says real-time is not live.
void main() {
  testWidgets(
    'subscribed, but GET /v1/realtime says live:false: the till says it polls, not "Jonli"',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      h.state.realtimeConfig = <String, Object?>{
        ...h.state.realtimeConfig!,
        'status': 'degraded',
        'live': false,
      };
      await pumpHome(tester, h);
      expect(find.text('Jonli'), findsNothing);
      expect(find.text('Har 15 soniyada'), findsOneWidget);
      expect(
        find.byTooltip(RegExp('Real-time javob bermayapti')),
        findsOneWidget,
      );

      // The heartbeat comes through after all: events can be trusted again
      h.hub.heartbeat(h.state.heartbeatBody());
      await tester.pumpAndSettle();
      expect(find.text('Jonli'), findsOneWidget);
    },
  );
}
