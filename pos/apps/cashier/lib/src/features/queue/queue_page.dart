import 'package:flutter/material.dart';

import '../../shell/feature_placeholder.dart';

/// Barcha kanallar navbati: yangi onlayn buyurtmalar, olib ketish, "fiskal kutmoqda" (P3-22).
class QueuePage extends StatelessWidget {
  const QueuePage({super.key});

  @override
  Widget build(BuildContext context) => const FeaturePlaceholder(
    icon: Icons.receipt_long_outlined,
    title: 'Navbat',
    message: "Barcha ochiq buyurtmalar (zal, olib ketish, onlayn) shu yerda bo'ladi.",
  );
}
