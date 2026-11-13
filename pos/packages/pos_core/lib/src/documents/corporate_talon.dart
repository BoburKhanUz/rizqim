import '../money.dart';
import 'common.dart';

/// P6-44: korxona xodimining taloni (chek qog'oziga chop etiladigan kartochka). "Talon" — xodimning kodi: QR sifatida
/// chop etiladi, kassada skanerlanadi yoki terib kiritiladi (`member_code`). Talon ishlatilib tugamaydi: kunlik limit va
/// kuniga ovqat soni serverda hisoblanadi, shuning uchun yo'qolgan yoki nusxalangan talon faqat egasining limiti
/// doirasida ishlaydi (yangi kod — panelda "Yangi talon kodi berish").
final class CorporateTalon {
  const CorporateTalon({
    required this.header,
    required this.company,
    required this.memberName,
    required this.code,
    required this.printedAt,
    this.dailyLimit,
    this.mealsPerDay,
    this.validNote,
  });

  final ReceiptHeader header;

  /// Korxona (hisob nomi yoki rasmiy nomi).
  final String company;
  final String memberName;

  /// Talon kodi (QR ichida ham shu).
  final String code;
  final DateTime printedAt;

  /// Kunlik limit (summa); `null` — cheksiz.
  final PosMoney? dailyLimit;

  /// Abonement: kuniga ovqat soni; `null` — cheklanmagan.
  final int? mealsPerDay;

  /// Qo'shimcha izoh ("Noyabr oyi uchun").
  final String? validNote;
}
