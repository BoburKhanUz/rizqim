import 'package:flutter/material.dart';

import '../../shell/feature_placeholder.dart';

/// Nasiya hisoblari va qarz to'lovi (P3-22).
class AccountsPage extends StatelessWidget {
  const AccountsPage({super.key});

  @override
  Widget build(BuildContext context) => const FeaturePlaceholder(
    icon: Icons.account_balance_wallet_outlined,
    title: 'Nasiya',
    message: "Nasiya hisoblari va qarz to'lovlari shu yerda bo'ladi.",
  );
}
