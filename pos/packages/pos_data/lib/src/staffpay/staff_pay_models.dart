import '../bootstrap/bootstrap_models.dart';
import '../json.dart';

/// Xodimning bitta ish vaqti (P6-23, docs/POS_SPEC.md §17): kelgan va ketgan vaqt, boshlangan biznes kuni.
class PosWorkSession {
  const PosWorkSession({
    required this.uuid,
    required this.businessDate,
    required this.clockInAt,
    this.clockOutAt,
    this.minutes = 0,
    this.open = true,
    this.abandoned = false,
  });

  factory PosWorkSession.fromJson(PosJson j) => PosWorkSession(
    uuid: j.str('uuid'),
    businessDate: j.str('business_date'),
    clockInAt: j.date('clock_in_at'),
    clockOutAt: j.dateOrNull('clock_out_at'),
    minutes: j.intOrNull('minutes') ?? 0,
    open: j.boolean('open', fallback: j.dateOrNull('clock_out_at') == null),
    abandoned: j.boolean('abandoned'),
  );

  final String uuid;

  /// `YYYY-MM-DD`.
  final String businessDate;
  final DateTime clockInAt;
  final DateTime? clockOutAt;

  /// Yopilgan ish vaqtining daqiqalari (ochiq bo'lsa 0).
  final int minutes;

  /// Xodim hali ishda.
  final bool open;

  /// Oldingi kunda ochiq qolgan va chetga olingan (menejer oxirini kiritguncha hisobga kirmaydi).
  final bool abandoned;
}

/// `GET /pos/clock`, `POST /pos/clock/in|out` javobi: kirgan xodimning ish vaqti holati.
class PosClockState {
  const PosClockState({
    this.session,
    this.todayMinutes = 0,
    required this.businessDate,
  });

  factory PosClockState.fromJson(PosJson j) {
    final PosJson? session = j.objOrNull('session');
    return PosClockState(
      session: session == null ? null : PosWorkSession.fromJson(session),
      todayMinutes: j.intOrNull('today_minutes') ?? 0,
      businessDate: j.str('business_date'),
    );
  }

  /// Ochiq ish vaqti; `null` — ishga kelgani belgilanmagan.
  final PosWorkSession? session;

  /// Bugungi (biznes kun) yopilgan ish vaqtlari daqiqalari.
  final int todayMinutes;
  final String businessDate;

  bool get clockedIn => session != null;
}

/// `GET /pos/bootstrap` → `staff_pay` (P6-23; `staff_pay` moduli yoqilgan filialda, bo'lmasa `null`): kassa va ofitsiant
/// ilovasi "Ishga keldim" / "Ishdan ketdim" tugmasini ko'rsatadimi va kirgan xodimning ochiq ish vaqti. Ish haqi summalari
/// terminalga hech qachon kelmaydi.
class PosStaffPayBlock {
  const PosStaffPayBlock({this.clock = false, this.session});

  factory PosStaffPayBlock.fromJson(PosJson j) {
    final PosJson? session = j.objOrNull('session');
    return PosStaffPayBlock(
      clock: j.boolean('clock'),
      session: session == null
          ? null
          : PosWorkSession(
              uuid: session.str('uuid'),
              businessDate: session.str('business_date'),
              clockInAt: session.date('clock_in_at'),
            ),
    );
  }

  /// Ish vaqtini belgilash tugmasi.
  final bool clock;

  /// Kirgan xodimning ochiq ish vaqti (`null` — hali kelmagan).
  final PosWorkSession? session;
}

/// Bootstrap'ning `staff_pay` qismi: konstruktorga tegmasdan [PosBootstrap.raw] dan o'qiladi.
extension PosBootstrapStaffPay on PosBootstrap {
  /// Modul o'chiq, eski server yoki o'qib bo'lmasa — `null` (tugma yo'q).
  PosStaffPayBlock? get staffPay {
    final Object? block = raw['staff_pay'];
    if (block is! Map) return null;
    try {
      return PosStaffPayBlock.fromJson(PosJson(block.cast<String, Object?>()));
    } on FormatException {
      return null;
    }
  }
}
