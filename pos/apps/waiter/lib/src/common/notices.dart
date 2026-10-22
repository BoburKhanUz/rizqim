import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Pastda qisqa xabar. [error] — xato (qizil). Bo'sh matn ko'rsatilmaydi.
void showNotice(BuildContext context, String message, {bool error = false}) {
  if (message.isEmpty) return;
  final RzColors c = context.rz;
  final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
  messenger
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        content: Text(
          message,
          style: RzType.body.copyWith(
            color: error ? c.onDanger : c.successSoftFg,
          ),
        ),
        backgroundColor: error ? c.danger : c.successSoft,
        behavior: SnackBarBehavior.floating,
        duration: Duration(seconds: error ? 6 : 3),
      ),
    );
}
