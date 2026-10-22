String _pad2(int n) => n.toString().padLeft(2, '0');

/// Chekdagi sana: "21.10.2026". Qiymat qanday berilsa shunday (mahalliy vaqtga o'tkazish chaqiruvchida).
String formatReceiptDate(DateTime d) =>
    '${_pad2(d.day)}.${_pad2(d.month)}.${d.year}';

/// "20:15" (24 soat).
String formatReceiptTime(DateTime d) => '${_pad2(d.hour)}:${_pad2(d.minute)}';

/// "21.10.2026 20:15".
String formatReceiptDateTime(DateTime d) =>
    '${formatReceiptDate(d)} ${formatReceiptTime(d)}';

/// Xona qancha vaqt ochiq: "0:45", "5:30", "26:05" (soat:daqiqa; kunga o'tkazilmaydi). Manfiy — "0:00".
String formatOpenDuration(Duration d) {
  if (d.isNegative) return '0:00';
  return '${d.inHours}:${_pad2(d.inMinutes % 60)}';
}

/// "12 kishi" kabi son + so'z (o'zbek tilida ko'plik qo'shimchasi songa qo'shilmaydi).
String countLabel(int count, String noun) => '$count $noun';
