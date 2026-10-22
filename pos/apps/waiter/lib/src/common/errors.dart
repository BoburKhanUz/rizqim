import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// Xatoning ofitsiantga ko'rsatiladigan o'zbekcha matni. Oyna yopilgani ([PosApprovalCancelled]) xato emas: bo'sh
/// matn qaytadi, chaqiruvchi hech narsa ko'rsatmaydi.
String describeError(Object error) {
  if (error is PosApprovalCancelled) return '';
  if (error is PosNetworkException) {
    return "Aloqa yo'q. Hozircha faqat ko'rish mumkin: aloqa qaytgach amalni takrorlang.";
  }
  if (error is PosVersionConflict) {
    return "Buyurtmani boshqa qurilma o'zgartirdi. Ro'yxat yangilandi, amalni qayta bajaring.";
  }
  if (error is PosApprovalRequired) {
    return "Bu buyurtma boshqa ofitsiantniki: uni o'zgartirish uchun menejer tasdig'i kerak.";
  }
  if (error is PosApprovalRejected) {
    return switch (error.reason) {
      PosReasons.approverNotAllowed =>
        "Bu xodim tasdiqlay olmaydi. Menejer yoki ega o'z PIN'ini kiritsin.",
      PosReasons.approvalExpired =>
        "Tasdiq muddati o'tdi. Menejer PIN'ini qayta kiriting.",
      PosReasons.approvalUsed =>
        "Bu tasdiq allaqachon ishlatilgan. Menejer PIN'ini qayta kiriting.",
      _ => error.message,
    };
  }
  if (error is PosValidationException) return error.firstError;
  if (error is ApiException) return error.message;
  return "Kutilmagan xato. Qayta urinib ko'ring.";
}
