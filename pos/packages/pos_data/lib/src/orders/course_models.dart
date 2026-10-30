import '../json.dart';

/// P6-21: buyurtmadagi bitta kurs qayerda (`courses[].status`): `held` — hali oshxonaga yuborilmagan, `sent` —
/// oshxonada, `ready` — yuborilgan hamma taomi tayyor, `served` — hammasi berilgan.
enum PosCourseStatus {
  held('held', 'Kutmoqda'),
  sent('sent', 'Oshxonada'),
  ready('ready', 'Tayyor'),
  served('served', 'Berildi');

  const PosCourseStatus(this.wire, this.label);

  final String wire;
  final String label;

  static PosCourseStatus fromWire(String? value) {
    for (final PosCourseStatus s in values) {
      if (s.wire == value) return s;
    }
    return held;
  }
}

/// P6-21 (`courses` moduli): buyurtma kursining qisqa holati (`GET /pos/orders/{uuid}` → `courses[]`). Keyingi kurs
/// "N-kursni yuborish" ([PosOrderRepository.sendCourse]) bilan oshxonaga chiqadi; oddiy "Oshxonaga yuborish" uni erta
/// yubormaydi.
class PosCourse {
  const PosCourse({
    required this.courseNo,
    required this.status,
    this.items = 0,
    this.sent = 0,
    this.ready = 0,
    this.served = 0,
  });

  factory PosCourse.fromJson(PosJson j) => PosCourse(
    courseNo: j.integer('course_no'),
    status: PosCourseStatus.fromWire(j.strOrNull('status')),
    items: j.intOrNull('items') ?? 0,
    sent: j.intOrNull('sent') ?? 0,
    ready: j.intOrNull('ready') ?? 0,
    served: j.intOrNull('served') ?? 0,
  );

  final int courseNo;
  final PosCourseStatus status;

  /// O'chirilmagan taomlar soni.
  final int items;
  final int sent;
  final int ready;
  final int served;

  /// "2-kurs".
  String get label => '$courseNo-kurs';

  /// Hali yuboriladigan taomi bor (kutayotgan kurs).
  bool get canSend => sent < items;
}

/// P6-25 (`weight_later` moduli): "og'irlikni keyin kiritish" qatori: `awaiting` — tanlangan, miqdori taxminiy,
/// og'irlik kiritilmaguncha schyot chiqmaydi (409 `weight_pending`); `weighed` — haqiqiy og'irlik kiritilgan.
enum PosWeightStatus {
  awaiting('awaiting', "Og'irlik kutilmoqda"),
  weighed('weighed', 'Tortildi');

  const PosWeightStatus(this.wire, this.label);

  final String wire;
  final String label;

  static PosWeightStatus? fromWire(String? value) {
    for (final PosWeightStatus s in values) {
      if (s.wire == value) return s;
    }
    return null;
  }
}

/// P6-25: kg narxi qaysi og'irlik uchun (`weight_basis` / mahsulotning `weight_mode`): xom (pishirishdan oldin) yoki
/// pishgan.
enum PosWeightBasis {
  raw('raw', 'xom'),
  cooked('cooked', 'pishgan');

  const PosWeightBasis(this.wire, this.label);

  final String wire;
  final String label;

  static PosWeightBasis? fromWire(String? value) {
    for (final PosWeightBasis b in values) {
      if (b.wire == value) return b;
    }
    return null;
  }
}
