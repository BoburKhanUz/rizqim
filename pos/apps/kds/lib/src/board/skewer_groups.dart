import 'package:flutter/foundation.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// Mangal chiptasida bir xil taomning (bir bosqichdagi) qatorlari bitta qatorga yig'iladi: "Qo'y — 6 six".
@immutable
class SkewerGroup {
  const SkewerGroup({
    required this.round,
    required this.title,
    required this.lines,
    required this.count,
    required this.piece,
    required this.done,
    required this.voided,
    required this.held,
    required this.quantityLabel,
    this.courseNo,
    this.awaitingWeight = false,
    this.timerLine,
  });

  final int? round;
  final String title;
  final List<KitchenLine> lines;

  /// Pishishi kerak (yoki hammasi tayyor bo'lsa — tayyor) donalar yig'indisi.
  final num count;

  /// Donali taom ("six"); vaznli taom (kg) alohida qatorda o'z yorlig'i bilan.
  final bool piece;

  /// Hamma qator tayyor yoki berilgan.
  final bool done;
  final bool voided;

  /// Hamma qator ushlab turilgan va vaqtsiz (oldindan buyurtma, hali yuborilmagan: "tayyor" deb bo'lmaydi).
  final bool held;

  /// Donasiz taomda server yorlig'i ("1.5 kg").
  final String quantityLabel;

  /// P6-21: guruhning kursi (guruh faqat bir kursning qatorlaridan iborat); kurssiz — `null`.
  final int? courseNo;

  /// P6-25: guruhning tortilmagan ("og'irlik kutilmoqda") qatori bor.
  final bool awaitingWeight;

  /// P6-25: mangal taymeri uchun qator: pishayotgan, vaqti belgilangan qatorlarning eng eski yuborilgani; yo'q — `null`.
  final KitchenLine? timerLine;

  /// Bosib "tayyor" qilinadigan qatorlar.
  List<KitchenLine> get bumpable =>
      lines.where((KitchenLine l) => l.canBeReady).toList();
}

/// [lines] ni (bosqich, taom, variant, modifikatorlar, izoh, holat guruhi) bo'yicha guruhlaydi; tartib birinchi
/// uchragan qator bo'yicha. Bir xil taomning boshqa kursdagi qatorlari birlashmaydi (P6-21).
///
/// O'chirilgan qatorlar alohida guruh bo'ladi (sanoqqa qo'shilmaydi). Donali bo'lmagan taom (kg, l) birlashtirilmaydi:
/// uning miqdor yorlig'i qatorniki. "Achchiq" yoki "yaxshi pishgan" qator oddiyidan alohida: guruh izohi hamma qatoriga
/// tegishli bo'ladi va bitta bosish faqat o'sha qatorlarni tayyor qiladi.
List<SkewerGroup> groupSkewers(List<KitchenLine> lines) {
  final Map<String, List<KitchenLine>> buckets = <String, List<KitchenLine>>{};
  for (final KitchenLine l in lines) {
    final String kind = l.isVoid ? 'void' : 'live';
    final String key = l.isPiece
        ? '${l.roundNo}|${l.courseNo}|${l.productName}|${l.variantName}|${_extras(l)}|$kind'
        : '${l.roundNo}|line${l.id}';
    buckets.putIfAbsent(key, () => <KitchenLine>[]).add(l);
  }
  return <SkewerGroup>[
    for (final List<KitchenLine> group in buckets.values) _group(group),
  ];
}

/// Qatorning modifikatorlari (tartibsiz) va izohi: bir xil bo'lsagina qatorlar birlashadi.
String _extras(KitchenLine l) {
  final List<String> modifiers = <String>[
    for (final KitchenModifier m in l.modifiers)
      '${m.groupName ?? ''}:${m.name}:${m.quantity}',
  ]..sort();
  return '${modifiers.join(',')}#${l.note?.trim() ?? ''}';
}

SkewerGroup _group(List<KitchenLine> g) {
  final KitchenLine first = g.first;
  final bool voided = first.isVoid;
  final List<KitchenLine> pending = g
      .where((KitchenLine l) => !l.isDone && !l.isVoid)
      .toList();
  // Pishishi kerak bo'lganlar sanaladi; hammasi tayyor bo'lsa — jami
  final List<KitchenLine> counted = pending.isNotEmpty ? pending : g;
  num count = 0;
  for (final KitchenLine l in counted) {
    count += l.quantity;
  }
  return SkewerGroup(
    round: first.roundNo,
    title: first.title,
    lines: g,
    count: count,
    piece: first.isPiece,
    done: !voided && pending.isEmpty,
    voided: voided,
    held:
        pending.isNotEmpty &&
        pending.every((KitchenLine l) => l.isHeld && l.dueAt == null),
    quantityLabel: first.quantityLabel,
    courseNo: first.courseNo,
    awaitingWeight: g.any((KitchenLine l) => !l.isVoid && l.awaitingWeight),
    timerLine: _oldestTimed(pending),
  );
}

/// Mangal vaqti belgilangan va yuborilgan qatorlardan eng eskisi.
KitchenLine? _oldestTimed(List<KitchenLine> lines) {
  KitchenLine? oldest;
  for (final KitchenLine l in lines) {
    if ((l.waitMinutes ?? 0) <= 0 || l.sentAt == null) continue;
    if (oldest == null || l.sentAt!.isBefore(oldest.sentAt!)) oldest = l;
  }
  return oldest;
}

/// Chiptadagi jami donalar (o'chirilgan va tayyorlari sanalmaydi): sarlavhada "10 six".
num pendingSkewers(Iterable<SkewerGroup> groups) {
  num total = 0;
  for (final SkewerGroup g in groups) {
    if (g.piece && !g.done && !g.voided && !g.held) total += g.count;
  }
  return total;
}
