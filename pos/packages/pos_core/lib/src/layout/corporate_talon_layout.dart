import '../documents/corporate_talon.dart';
import '../format.dart';
import '../print/print_line.dart';
import '../print/print_writer.dart';
import 'receipt_layout.dart';

/// P6-44: korxona xodimi taloni → qatorlar: do'kon, korxona, xodim, QR (talon kodi), kod yozuvda, limitlar.
PrintLayout layoutCorporateTalon(CorporateTalon t, PaperWidth paper) {
  final PrintWriter w = PrintWriter(paper);
  w.center(t.header.storeName, bold: true);
  w.divider();
  w.center('KORXONA TALONI', bold: true);
  w.center(t.company, bold: true);
  w.feed();
  w.center(t.memberName, bold: true, size: PrintTextSize.tall);
  w.feed();
  w.qr(t.code);
  w.center(t.code, bold: true);
  w.divider();
  w.pair(
    'Kunlik limit:',
    t.dailyLimit != null ? paperAmount(t.dailyLimit!) : 'cheksiz',
  );
  if (t.mealsPerDay != null) {
    w.pair('Kuniga ovqat:', '${t.mealsPerDay} marta');
  }
  if (t.validNote != null) w.center(t.validNote!);
  w.divider();
  w.center('Kassada QR kodni ko\'rsating.');
  w.center('Talon faqat egasiga tegishli.');
  w.center(formatReceiptDateTime(t.printedAt));
  w.cut();
  return w.build(title: 'Talon ${t.code}');
}
