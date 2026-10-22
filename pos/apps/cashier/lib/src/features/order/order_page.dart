import 'package:flutter/material.dart';

import '../../shell/feature_placeholder.dart';

/// Buyurtma muharriri: menyu, kg klaviaturasi, qo'shimchalar, bosqichlar, oshxonaga yuborish (P3-21). Mavjud buyurtma
/// ([orderUuid]) yoki joyda yangisi ([tableId]); ikkalasi `null` — stolsiz yoki olib ketish.
class OrderPage extends StatelessWidget {
  const OrderPage({super.key, this.orderUuid, this.tableId});

  final String? orderUuid;
  final int? tableId;

  @override
  Widget build(BuildContext context) => const FeaturePlaceholder(
    icon: Icons.restaurant_menu_outlined,
    title: 'Buyurtma',
    message: "Menyu va buyurtma bosqichlari shu yerda bo'ladi.",
    showAppBar: true,
  );
}
