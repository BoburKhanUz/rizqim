import '../documents/gift_certificate.dart';
import '../format.dart';
import '../print/print_line.dart';
import '../print/print_writer.dart';
import 'receipt_layout.dart';

/// P6-43: sovg'a sertifikati → qatorlar: do'kon, "SOVG'A SERTIFIKATI", qiymat (katta), QR (kod), kod yozuvda, muddat.
PrintLayout layoutGiftCertificate(GiftCertificate g, PaperWidth paper) {
  final PrintWriter w = PrintWriter(paper);
  writeHeader(w, g.header);
  w.divider('=');
  w.center("SOVG'A SERTIFIKATI", bold: true, size: PrintTextSize.tall);
  if (g.isCopy) writeCopyMark(w);
  w.feed();
  w.center(
    "${paperAmount(g.value)} so'm",
    bold: true,
    size: PrintTextSize.large,
  );
  if (g.buyerName != null && g.buyerName!.trim().isNotEmpty) {
    w.center('Kimga: ${g.buyerName!.trim()}');
  }
  w.feed();
  w.qr(g.qr);
  w.center(g.code, bold: true, size: PrintTextSize.tall);
  w.divider();
  w.pair('Sotildi:', formatReceiptDate(g.validFrom));
  w.pair(
    'Amal qiladi:',
    g.validUntil == null
        ? 'muddatsiz'
        : '${formatReceiptDate(g.validUntil!)} gacha',
  );
  w.divider();
  w.center("Kassada QR kodni ko'rsating.");
  w.center('Bir necha marta ishlatiladi:');
  w.center('qoldiq keyingi xaridga qoladi.');
  w.center('Naqd pulga almashtirilmaydi.');
  w.center(formatReceiptDateTime(g.printedAt));
  w.cut();
  return w.build(title: 'Sertifikat ${g.code}');
}
