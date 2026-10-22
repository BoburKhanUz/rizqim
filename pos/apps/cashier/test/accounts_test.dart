import 'package:flutter_test/flutter_test.dart';

import 'support/harness.dart';
import 'support/p22.dart';

void main() {
  testWidgets(
    'debtors with the total debt, search by phone, statement, repayment prints a CREDIT receipt',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String jamshid = p.backend.seedAccount(
        name: 'Jamshid aka',
        phone: '+998901112233',
        group: 'Gap guruhi',
        limit: '2000000.00',
        debt: '850000.00',
      );
      p.backend.seedAccount(
        name: 'Sardor Rahimov',
        phone: '+998935554433',
        debt: '320000.00',
      );
      p.backend.seedAccount(name: 'Qarzsiz', phone: '+998970000000');
      await pumpHome(tester, p.h);
      await openSection(tester, 'Nasiya');

      expect(find.text(uzs(1170000)), findsOneWidget);
      expect(find.text('2 qarzdor'), findsOneWidget);
      expect(find.text('Jamshid aka'), findsOneWidget);
      expect(find.text('Sardor Rahimov'), findsOneWidget);
      expect(find.text('Qarzsiz'), findsNothing);
      // Kassirda customer_accounts.manage yo'q: hisob ochish tugmasi yo'q
      expect(find.text('Yangi hisob'), findsNothing);

      await enter(tester, 'accounts-search', '1112');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      expect(find.text('Sardor Rahimov'), findsNothing);
      await tester.tap(find.text('Jamshid aka'));
      await tester.pumpAndSettle();
      expect(find.text("Ko'chirma"), findsOneWidget);
      expect(find.text('Nasiyaga yopilgan hisob'), findsOneWidget);
      expect(find.text(uzs(1150000)), findsOneWidget); // yana mumkin

      await tapButton(tester, "Qarz to'lovi");
      // Qarzdan ko'p bo'lmaydi
      await enter(tester, 'tender-amount', '900000');
      await tapKey(tester, 'repay-submit');
      expect(find.textContaining("Summa qarzdan ko'p"), findsOneWidget);
      await enter(tester, 'tender-amount', '350000');
      await tapKey(tester, 'repay-submit');

      expect(p.backend.accounts[jamshid]!['balance'], '500000.00');
      final String receipt = p.receipts.single;
      expect(receipt, contains('KREDIT CHEKI'));
      expect(receipt, contains("Qarz to'lovi"));
      expect(receipt, contains('Mijoz: Jamshid aka'));
      expect(receipt, contains('Qolgan qarz:'));
      expect(receipt, contains('500 000'));
      expect(receipt, isNot(contains('Fiskal belgi')));
      expect(p.drawerKicks, 1);
      expect(find.text(uzs(500000)), findsWidgets);
      expect(find.text("Qarz to'lovi"), findsWidgets);
    },
  );

  testWidgets('a manager opens an account with a limit and blocks new credit', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    await pumpHome(tester, p.h, name: 'Malika Rahimova');
    await openSection(tester, 'Nasiya');
    expect(find.text("Qarzdor yo'q"), findsOneWidget);
    await tapButton(tester, 'Yangi hisob');
    await enter(tester, 'account-name', 'Bekzod');
    await enter(tester, 'account-phone', '90 777 66 55');
    await enter(tester, 'account-limit', '500000');
    await tapButton(tester, 'Saqlash');
    final Map<String, Object?> a = p.backend.accounts.values.single;
    expect(a['name'], 'Bekzod');
    expect(a['phone'], '+998907776655');
    expect(a['credit_limit'], '500000.00');

    await tapButton(tester, 'Tahrirlash');
    await tapOn(tester, find.text("Yangi nasiya to'xtatilgan"));
    await tapButton(tester, 'Saqlash');
    expect(a['is_blocked'], isTrue);
    expect(find.text("Yangi nasiya to'xtatilgan"), findsWidgets);
  });
}
