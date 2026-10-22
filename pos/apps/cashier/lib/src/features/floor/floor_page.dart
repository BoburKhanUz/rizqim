import 'package:flutter/material.dart';

import '../../shell/feature_placeholder.dart';

/// Zal xaritasi: xonalar, tapchanlar, stollar holati, bron belgisi, necha soat ochiq; tez kassa (P3-21).
class FloorPage extends StatelessWidget {
  const FloorPage({super.key});

  @override
  Widget build(BuildContext context) => const FeaturePlaceholder(
    icon: Icons.table_restaurant_outlined,
    title: 'Zal xaritasi',
    message: "Xonalar, tapchanlar va stollar shu yerda bo'ladi.",
  );
}
