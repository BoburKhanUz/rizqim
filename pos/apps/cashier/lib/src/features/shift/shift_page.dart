import 'package:flutter/material.dart';

import '../../shell/feature_placeholder.dart';

/// Smena: ochish, kirim/chiqim, X va Z (P3-22).
class ShiftPage extends StatelessWidget {
  const ShiftPage({super.key});

  @override
  Widget build(BuildContext context) => const FeaturePlaceholder(
    icon: Icons.point_of_sale_outlined,
    title: 'Smena',
    message:
        "Smenani ochish, kassa harakatlari, X va Z hisobot shu yerda bo'ladi.",
  );
}
