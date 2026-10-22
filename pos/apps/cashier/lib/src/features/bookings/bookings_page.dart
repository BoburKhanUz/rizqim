import 'package:flutter/material.dart';

import '../../shell/feature_placeholder.dart';

/// Bron taqvimi (xona × vaqt), bron yaratish, avans, "Keldi" (P3-22).
class BookingsPage extends StatelessWidget {
  const BookingsPage({super.key});

  @override
  Widget build(BuildContext context) => const FeaturePlaceholder(
    icon: Icons.event_outlined,
    title: 'Bronlar',
    message: "Bron taqvimi va avanslar shu yerda bo'ladi.",
  );
}
