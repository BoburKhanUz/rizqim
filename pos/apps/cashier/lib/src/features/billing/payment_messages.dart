import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';

/// P6-41/P6-43/P6-44 (to'lqin 4, B): QR, sertifikat, promo-kod va korxona hisobi xatolarining kassirga oddiy matni.
/// Korxona va sertifikat rad etilganda — har doim naqd yoki karta taklif qilinadi (to'lov to'xtamaydi). Noma'lum kod —
/// `null` (umumiy xato matni ko'rsatiladi).
String? paymentRefusalText(Object error) {
  if (error is! PosException) return null;
  if (error is PosOfflineUnavailable) return error.message;
  final Map<String, Object?> d = error.data;
  String money(String key) {
    final Object? v = d[key];
    if (v == null) return '';
    try {
      return PosMoney.parse('$v').format();
    } on FormatException {
      return '$v';
    }
  }

  const String cashOrCard = 'Naqd yoki karta bilan oling.';
  return switch (error.reason) {
    // Korxona hisobi (P6-44)
    'member_daily_limit' =>
      "Xodimning kunlik limiti yetmaydi: bugun yana ${money('available')} mumkin. Qolganini naqd yoki karta bilan oling.",
    'member_meals_used' =>
      "Bu xodim bugungi ovqatini olgan (abonement). $cashOrCard",
    'account_monthly_limit' =>
      "Korxonaning oylik limiti tugagan (qoldi ${money('available')}). $cashOrCard",
    'account_credit_limit' =>
      "Korxona hisobining qarz limiti yetmaydi. $cashOrCard",
    'member_inactive' => "Xodimning taloni to'xtatilgan. $cashOrCard",
    'member_required' =>
      'Xodimning talon kodini skanerlang yoki telefonini kiriting.',
    'member_not_found' => "Bunday talon (xodim) topilmadi. Kodni tekshiring yoki naqd/karta bilan oling.",
    'account_blocked' => "Bu hisobga yozish to'xtatilgan. $cashOrCard",
    // Sertifikat (P6-43)
    'voucher_not_found' => 'Sertifikat topilmadi: kodni tekshiring.',
    'voucher_insufficient' =>
      "Sertifikatda yetarli mablag' yo'q: qoldiq ${money('balance')}. Qolganini naqd yoki karta bilan oling.",
    'voucher_expired' => "Sertifikat muddati o'tgan. $cashOrCard",
    'voucher_blocked' =>
      'Sertifikat bloklangan (menejerga murojaat qiling). $cashOrCard',
    'voucher_used' => "Sertifikat to'liq ishlatilgan. $cashOrCard",
    'voucher_already_on_bill' => 'Bu sertifikat shu schyotda ishlatilgan.',
    'face_value_invalid' =>
      "Qiymat 1 000 dan 100 000 000 so'mgacha butun son bo'lsin.",
    // QR (P6-41, P6-42)
    'provider_not_configured' =>
      "${_provider(d['provider'])} do'konning o'z merchant hisobiga ulanmagan. Boshqa provayderni tanlang yoki statik QR'ni qo'lda tasdiqlang.",
    'qr_provider_disabled' =>
      "Bu provayder filialda o'chirilgan. Boshqasini tanlang.",
    'qr_already_paid' => "Bu QR allaqachon to'langan.",
    'qr_refund_failed' =>
      "${_provider(d['provider'])} to'lovni qaytarmadi. QR to'lovi naqd bilan qaytarilmaydi: birozdan keyin qayta urinib ko'ring yoki menejerga ayting.",
    // Review P6 wave 4 (C2): the provider did not answer (or the refund is still being asked): never cash
    'qr_refund_unknown' =>
      "To'lov tizimi javob bermadi — holat tekshirilmoqda. Pul mijozga qaytgan bo'lsa, qaytarish o'zi yoziladi. "
          "Birozdan so'ng \"Holatni tekshirish\" ni bosing; naqd bilan qaytarmang.",
    'qr_partial_refund_unsupported' =>
      "QR to'lovini faqat to'liq qaytarish mumkin: ${money('left')}. Summani to'liq kiriting.",
    'bill_paid' => "Schyot allaqachon to'langan.",
    'overpayment' => "Summa schyot qoldig'idan ko'p: qoldiq ${money('due')}.",
    // Promo-kod (P6-43)
    'promo_not_found' => 'Promo-kod topilmadi: kodni tekshiring.',
    'promo_inactive' =>
      "Promo-kod faol emas (muddati o'tgan yoki o'chirilgan).",
    'promo_min_order' =>
      "Promo-kod ${money('min_order_amount')} dan katta buyurtmaga ishlaydi.",
    'promo_limit_reached' => "Promo-kodning ishlatish limiti tugagan.",
    'promo_no_discount' => 'Bu buyurtmaga promo-kod chegirma bermaydi.',
    'promo_already_applied' =>
      'Buyurtmada promo-kod bor: avval uni olib tashlang.',
    'promo_not_applied' => "Buyurtmada promo-kod yo'q.",
    _ => null,
  };
}

String _provider(Object? wire) =>
    PosQrProvider.fromWire('${wire ?? ''}')?.label ?? 'Provayder';

/// X/Z dagi QR provayder nomi (`manual` — qo'lda tasdiqlangan statik QR).
String qrProviderLabel(String wire) => wire == 'manual'
    ? "Statik QR (qo'lda)"
    : (PosQrProvider.fromWire(wire)?.label ?? wire);
