import 'package:pos_core/pos_core.dart';

import '../json.dart';
import '../pickup/queue_number_models.dart';

/// P6-14: mijoz ekrani nima ko'rsatadi (docs/POS_SPEC.md 2.14a). Kassa joriy chekni `POST /pos/display/cart` bilan
/// yuboradi, ekran `GET /pos/display/state` dan o'qiydi.
enum PosDisplayScreen {
  /// Chek yo'q: salomlashish (yoki "Tayyor" raqamlar).
  idle('idle'),

  /// Taomlar qo'shilmoqda.
  cart('cart'),

  /// To'lov: summa va (kassa ko'rsatsa) to'lov QR'i.
  payment('payment'),

  /// To'landi: "Rahmat".
  paid('paid');

  const PosDisplayScreen(this.wire);

  final String wire;

  static PosDisplayScreen fromWire(String? wire) {
    for (final PosDisplayScreen s in values) {
      if (s.wire == wire) return s;
    }
    return idle;
  }
}

/// Mijoz ekranidagi bitta qator.
class PosDisplayLine {
  const PosDisplayLine({
    required this.name,
    required this.quantity,
    required this.price,
    required this.total,
    this.unit,
    this.modifiers = const <String>[],
    this.note,
  });

  factory PosDisplayLine.fromJson(PosJson j) => PosDisplayLine(
    name: j.str('name'),
    quantity: j.quantity('qty', fallback: PosQuantity.one),
    unit: j.strOrNull('unit'),
    price: j.money('price'),
    total: j.money('total'),
    modifiers: j.strings('modifiers'),
    note: j.strOrNull('note'),
  );

  final String name;
  final PosQuantity quantity;

  /// Og'irlikli taomda `kg`, donalida `null`.
  final String? unit;
  final PosMoney price;
  final PosMoney total;
  final List<String> modifiers;
  final String? note;

  Map<String, Object?> toJson() => <String, Object?>{
    'name': name,
    'qty': quantity.toApiString(),
    'unit': unit,
    'price': price.toApiString(),
    'total': total.toApiString(),
    'modifiers': modifiers,
    'note': note,
  };
}

/// To'lov QR'i: kassa ko'rsatsa (Click, Payme ...), ekran [payload] matnini QR qilib chizadi.
class PosDisplayQr {
  const PosDisplayQr({required this.payload, this.label, this.provider});

  factory PosDisplayQr.fromJson(PosJson j) => PosDisplayQr(
    payload: j.str('payload'),
    label: j.strOrNull('label'),
    provider: j.strOrNull('provider'),
  );

  final String payload;
  final String? label;
  final String? provider;
}

/// To'lov qismi: usul, olingan summa va qaytim, QR.
class PosDisplayPayment {
  const PosDisplayPayment({this.method, this.tendered, this.change, this.qr});

  factory PosDisplayPayment.fromJson(PosJson j) => PosDisplayPayment(
    method: j.strOrNull('method'),
    tendered: j.moneyOrNull('tendered'),
    change: j.moneyOrNull('change'),
    qr: j.objOrNull('qr') == null ? null : PosDisplayQr.fromJson(j.obj('qr')),
  );

  final String? method;
  final PosMoney? tendered;
  final PosMoney? change;
  final PosDisplayQr? qr;
}

/// Kassa ikkinchi ekranda ko'rsatayotgan chek (`cart`).
class PosDisplayCart {
  const PosDisplayCart({
    required this.state,
    this.lines = const <PosDisplayLine>[],
    this.subtotal = PosMoney.zero,
    this.discount = PosMoney.zero,
    this.total = PosMoney.zero,
    this.queueNumber,
    this.payment,
    this.seq = 0,
    this.session,
    this.terminal,
    this.updatedAt,
  });

  /// Chek yo'q (yangi mijoz kutilmoqda).
  const PosDisplayCart.idle() : this(state: PosDisplayScreen.idle);

  factory PosDisplayCart.fromJson(PosJson j) => PosDisplayCart(
    state: PosDisplayScreen.fromWire(j.strOrNull('state')),
    lines: j.objects('lines', PosDisplayLine.fromJson),
    subtotal: j.money('subtotal'),
    discount: j.money('discount'),
    total: j.money('total'),
    queueNumber: j.intOrNull('queue_number'),
    payment: j.objOrNull('payment') == null
        ? null
        : PosDisplayPayment.fromJson(j.obj('payment')),
    seq: j.intOrNull('seq') ?? 0,
    session: j.strOrNull('session'),
    terminal: j.strOrNull('terminal'),
    updatedAt: j.dateOrNull('updated_at'),
  );

  final PosDisplayScreen state;
  final List<PosDisplayLine> lines;
  final PosMoney subtotal;
  final PosMoney discount;
  final PosMoney total;

  /// Buyurtmaning navbat raqami (`queue_numbers` moduli).
  final int? queueNumber;
  final PosDisplayPayment? payment;

  /// Kassa yuborgan tartib raqami (eskirgan yuborish tashlanadi).
  final int seq;

  /// Kassa ilovasining ishga tushishi (qayta ochilganda `seq` 1 dan boshlanadi).
  final String? session;

  /// Qaysi kassa (qurilma uuid).
  final String? terminal;
  final DateTime? updatedAt;

  bool get isIdle => state == PosDisplayScreen.idle;

  /// `POST /pos/display/cart` tanasi (`seq`, `session` ni [DisplayFeed] qo'yadi).
  Map<String, Object?> toJson() => <String, Object?>{
    'state': state.wire,
    'seq': seq,
    'session': session,
    'lines': <Map<String, Object?>>[
      for (final PosDisplayLine l in lines) l.toJson(),
    ],
    'subtotal': subtotal.toApiString(),
    'discount': discount.toApiString(),
    'total': total.toApiString(),
    'queue_number': queueNumber,
    'payment': payment == null
        ? null
        : <String, Object?>{
            'method': payment!.method,
            'tendered': payment!.tendered?.toApiString(),
            'change': payment!.change?.toApiString(),
            'qr': payment!.qr == null
                ? null
                : <String, Object?>{
                    'payload': payment!.qr!.payload,
                    'label': payment!.qr!.label,
                    'provider': payment!.qr!.provider,
                  },
          },
  };

  PosDisplayCart copyWith({int? seq, String? session}) => PosDisplayCart(
    state: state,
    lines: lines,
    subtotal: subtotal,
    discount: discount,
    total: total,
    queueNumber: queueNumber,
    payment: payment,
    seq: seq ?? this.seq,
    session: session ?? this.session,
    terminal: terminal,
    updatedAt: updatedAt,
  );
}

/// Ekran qaysi rejimlarni ko'rsata oladi (formatdagi modullar, [PosModule]).
class PosDisplayModules {
  const PosDisplayModules({
    this.customerDisplay = false,
    this.queueCalling = false,
  });

  factory PosDisplayModules.fromJson(PosJson j) => PosDisplayModules(
    customerDisplay: j.boolean('customer_display'),
    queueCalling: j.boolean('queue_calling'),
  );

  /// Kassa ikkinchi ekrani (joriy chek).
  final bool customerDisplay;

  /// "Tayyorlanmoqda / Tayyor" raqamlar taxtasi.
  final bool queueCalling;

  bool get any => customerDisplay || queueCalling;
}

/// `GET /pos/display/state`: ekranning filiali, kuzatadigan kassasi, modullari va joriy cheki.
class PosDisplayState {
  const PosDisplayState({
    required this.branchId,
    required this.branchName,
    required this.deviceName,
    required this.modules,
    required this.serverTime,
    this.storeName,
    this.tillUuid,
    this.cart,
  });

  factory PosDisplayState.fromJson(PosJson j) => PosDisplayState(
    branchId: j.obj('branch').integer('id'),
    branchName: j.obj('branch').str('name'),
    storeName: j.objOrNull('store')?.strOrNull('name'),
    deviceName: j.obj('device').str('name'),
    tillUuid: j.strOrNull('till_uuid'),
    modules: PosDisplayModules.fromJson(j.obj('modules')),
    serverTime: j.date('server_time'),
    cart: j.objOrNull('cart') == null
        ? null
        : PosDisplayCart.fromJson(j.obj('cart')),
  );

  final int branchId;
  final String branchName;
  final String? storeName;
  final String deviceName;

  /// Ekran shu kassaning chekini ko'rsatadi; `null` — filialning oxirgisi.
  final String? tillUuid;
  final PosDisplayModules modules;

  /// Server soati: chekning eskirganini qurilma soatiga bog'liqsiz hal qilish uchun.
  final DateTime serverTime;
  final PosDisplayCart? cart;

  /// Chek serverda necha vaqt oldin yangilangan (server soati bo'yicha).
  Duration? get cartAge {
    final DateTime? at = cart?.updatedAt;
    return at == null ? null : serverTime.difference(at);
  }
}

/// "Tayyorlanmoqda / Tayyor" taxtasi mijoz ekranida: [PosQueueBoard] (`GET /pos/display/queue`, kassadagi
/// `GET /pos/queue` bilan bir xil javob).
typedef PosDisplayBoard = PosQueueBoard;
