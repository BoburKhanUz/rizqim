import 'package:flutter/material.dart';

import '../../shell/feature_placeholder.dart';

/// Sozlamalar: printerlar (sinov chop etish), ovoz (P3-22).
class SettingsPage extends StatelessWidget {
  const SettingsPage({super.key});

  @override
  Widget build(BuildContext context) => const FeaturePlaceholder(
    icon: Icons.settings_outlined,
    title: 'Sozlamalar',
    message: "Printerlar va boshqa sozlamalar shu yerda bo'ladi.",
  );
}
