import 'package:flutter/material.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'qr_encoder.dart';

/// To'lov QR'i: oq maydonda qora modullar (mavzudan qat'i nazar; telefon kamerasi shu bilan o'qiydi) va 4 modullik
/// bo'sh chekka. Havola sig'maydigan darajada uzun bo'lsa, QR o'rniga tushuntirish.
class QrView extends StatelessWidget {
  const QrView({super.key, required this.payload, this.size = 280});

  final String payload;
  final double size;

  @override
  Widget build(BuildContext context) {
    QrCode? qr;
    try {
      qr = QrCode.encode(payload);
    } on QrTooLongException {
      qr = null;
    }
    if (qr == null) {
      final RzColors c = context.rz;
      return SizedBox(
        width: size,
        height: size,
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: c.surface2,
            borderRadius: BorderRadius.circular(RzRadius.card),
          ),
          child: Center(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Text(
                "QR kodni ko'rsatib bo'lmadi. To'lovni kassir bilan hal qiling.",
                textAlign: TextAlign.center,
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
            ),
          ),
        ),
      );
    }
    return Semantics(
      label: "To'lov QR kodi",
      image: true,
      child: SizedBox(
        width: size,
        height: size,
        child: CustomPaint(painter: _QrPainter(qr)),
      ),
    );
  }
}

class _QrPainter extends CustomPainter {
  _QrPainter(this.qr);

  final QrCode qr;

  static const int _quiet = 4;

  @override
  void paint(Canvas canvas, Size size) {
    final int modules = qr.size + 2 * _quiet;
    final double cell = size.shortestSide / modules;
    canvas.drawRRect(
      RRect.fromRectAndRadius(Offset.zero & size, const Radius.circular(12)),
      Paint()..color = const Color(0xFFFFFFFF),
    );
    final Paint dark = Paint()..color = const Color(0xFF000000);
    for (var r = 0; r < qr.size; r++) {
      for (var c = 0; c < qr.size; c++) {
        if (!qr.isDark(r, c)) continue;
        canvas.drawRect(
          Rect.fromLTWH(
            (c + _quiet) * cell,
            (r + _quiet) * cell,
            cell + 0.4, // modullar orasida oq chiziq qolmasin
            cell + 0.4,
          ),
          dark,
        );
      }
    }
  }

  @override
  bool shouldRepaint(_QrPainter old) => old.qr != qr;
}
