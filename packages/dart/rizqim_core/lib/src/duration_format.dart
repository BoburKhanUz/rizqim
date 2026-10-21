/// Davomiylik soat ko'rinishida: `4:05` (daqiqa:soniya), bir soatdan oshsa `1:02:03`. Manfiy qiymat `0:00`.
///
/// Oshxona chiptasining kutish vaqti va PIN bloklanishining qolgan vaqti uchun.
String formatDuration(Duration value) {
  final int total = value.isNegative ? 0 : value.inSeconds;
  final int hours = total ~/ 3600;
  final int minutes = (total % 3600) ~/ 60;
  final int seconds = total % 60;
  final String ss = seconds.toString().padLeft(2, '0');
  if (hours == 0) return '$minutes:$ss';
  return '$hours:${minutes.toString().padLeft(2, '0')}:$ss';
}
