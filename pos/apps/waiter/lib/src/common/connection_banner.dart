import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Aloqa uzilganda yuqorida chiqadigan lenta: ilova faqat ko'rish rejimida (yozuvlar navbatga qo'yilmaydi) va aloqa
/// qaytguncha shunday qoladi. Aloqa qaytsa o'zi yo'qoladi.
class ConnectionBanner extends StatelessWidget {
  const ConnectionBanner({super.key, required this.connectivity, this.onRetry});

  final PosConnectivity connectivity;

  /// "Qayta urinish": ma'lumotni hozir yuklash.
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => StreamBuilder<PosConnectivityState>(
    stream: connectivity.changes,
    initialData: connectivity.state,
    builder: (BuildContext context, AsyncSnapshot<PosConnectivityState> snap) {
      final PosConnectivityState state = snap.data ?? connectivity.state;
      if (state.online) return const SizedBox.shrink();
      return RzOfflineBanner(
        title: "Aloqa yo'q",
        message: 'Faqat ko\'rish mumkin.',
        actionLabel: onRetry == null ? null : 'Qayta urinish',
        onAction: onRetry,
      );
    },
  );
}

/// Yozuv amallari (yuborish, qo'shish ...) uchun: server javob beryaptimi.
bool isOnline(PosConnectivity connectivity) => connectivity.state.online;
