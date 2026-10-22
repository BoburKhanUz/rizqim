import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// "Terminalni uzish" (faqat menejer va ega: [PosSession.canUnpair]): tasdiqlangach terminal serverda o'zini bekor
/// qiladi (`DELETE /terminal`: tokenlari va xodim sessiyalari to'xtaydi) va qurilmadan unutiladi. Server javob bermasa (tarmoq yo'q), qurilma baribir uziladi va
/// serverdagi yozuvni panelda o'chirish kerakligi aytiladi.
Future<void> confirmUnpair(BuildContext context, PosSession session) async {
  // The messenger outlives this screen: after unpairing the app shows the pairing screen
  final ScaffoldMessengerState? messenger = ScaffoldMessenger.maybeOf(context);
  final bool? confirmed = await showDialog<bool>(
    context: context,
    builder: (BuildContext context) => AlertDialog(
      title: const Text('Terminalni uzish'),
      content: const Text(
        "Bu telefon do'kondan uziladi va serverda ham o'chiriladi. Qayta ulash uchun yangi kod kerak bo'ladi.",
      ),
      actions: <Widget>[
        RzButton(
          label: 'Bekor qilish',
          variant: RzButtonVariant.ghost,
          onPressed: () => Navigator.pop(context, false),
        ),
        RzButton(
          label: 'Uzish',
          variant: RzButtonVariant.danger,
          onPressed: () => Navigator.pop(context, true),
        ),
      ],
    ),
  );
  if (confirmed != true) return;
  final bool revoked = await session.unpair();
  if (!revoked) {
    messenger?.showSnackBar(
      const SnackBar(
        content: Text(
          "Server bilan aloqa bo'lmadi: qurilma bu yerda uzildi. Uni boshqaruv panelida Sozlamalar → Qurilmalar "
          "ro'yxatidan ham o'chiring.",
        ),
        duration: Duration(seconds: 10),
      ),
    );
  }
}
