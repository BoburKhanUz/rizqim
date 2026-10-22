import 'package:flutter/material.dart';

import '../../shell/feature_placeholder.dart';

/// Schyot, hisobni bo'lish va to'lov (P3-22).
class BillingPage extends StatelessWidget {
  const BillingPage({super.key, required this.orderUuid});

  final String orderUuid;

  @override
  Widget build(BuildContext context) => const FeaturePlaceholder(
    icon: Icons.payments_outlined,
    title: "Schyot va to'lov",
    message: "Schyot, bo'lish va to'lov shu yerda bo'ladi.",
    showAppBar: true,
  );
}
