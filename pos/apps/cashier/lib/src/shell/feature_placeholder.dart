import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Hali qurilmagan bo'lim sahifasi (qobiq ishida): bo'lim nomi va qisqa tavsif. Feature egasi o'z `*_page.dart`
/// faylini almashtirganda bu vidjet ishlatilmay qoladi.
class FeaturePlaceholder extends StatelessWidget {
  const FeaturePlaceholder({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    this.showAppBar = false,
  });

  final IconData icon;
  final String title;
  final String message;

  /// Ustidan ochilgan sahifa (buyurtma, schyot) — orqaga tugmasi bilan.
  final bool showAppBar;

  @override
  Widget build(BuildContext context) {
    final Widget body = EmptyState(icon: icon, title: title, message: message);
    if (!showAppBar) return ColoredBox(color: context.rz.page, child: body);
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: body,
    );
  }
}
