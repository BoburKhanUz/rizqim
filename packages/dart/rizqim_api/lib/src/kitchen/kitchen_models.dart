/// Oshxona chiptasi (`GET /v1/staff/kitchen/tickets`, docs/API.md 9.1). Yaratilgan `Data80`/`Data83` modellari
/// ustidagi barqaror, qo'lda yozilgan ko'rinish: yaratilgan nomlar spetsifikatsiya bilan o'zgarishi mumkin, ilovalar
/// shu sinflarga tayanadi.
library;

/// Qatorning oshxonadagi holati (`order_items.kitchen_status`). POS bosqichida yangi qiymatlar qo'shiladi
/// (`sent`, `served`, `void`), shuning uchun satr sifatida saqlanadi.
abstract final class KitchenLineStatus {
  static const String pending = 'pending';
  static const String ready = 'ready';
}

/// Taomga qo'shilgan modifikator ("Qo'shimcha: pishloq").
class KitchenModifier {
  const KitchenModifier({
    required this.name,
    this.groupName,
    this.quantity = 1,
  });

  factory KitchenModifier.fromJson(Map<String, Object?> json) =>
      KitchenModifier(
        name: '${json['name'] ?? ''}',
        groupName: json['group_name'] as String?,
        quantity: (json['quantity'] as num?)?.toInt() ?? 1,
      );

  final String name;
  final String? groupName;
  final int quantity;

  /// "pishloq" yoki "pishloq ×2".
  String get label => quantity > 1 ? '$name ×$quantity' : name;
}

/// Chiptadagi bitta taom qatori.
class KitchenLine {
  const KitchenLine({
    required this.id,
    required this.productName,
    required this.quantityLabel,
    required this.status,
    this.variantName,
    this.quantity = 1,
    this.unit = 'pcs',
    this.modifiers = const <KitchenModifier>[],
    this.readyAt,
  });

  factory KitchenLine.fromJson(Map<String, Object?> json) => KitchenLine(
    id: (json['id']! as num).toInt(),
    productName: '${json['product_name'] ?? ''}',
    variantName: json['variant_name'] as String?,
    quantity: (json['quantity'] as num?) ?? 1,
    unit: (json['unit'] as String?) ?? 'pcs',
    quantityLabel: '${json['quantity_label'] ?? json['quantity'] ?? ''}',
    modifiers: _list(json['modifiers'], KitchenModifier.fromJson),
    status: (json['kitchen_status'] as String?) ?? KitchenLineStatus.pending,
    readyAt: _date(json['ready_at']),
  );

  final int id;
  final String productName;
  final String? variantName;

  /// Donali taomda butun son, kg/l da kasr.
  final num quantity;

  /// `pcs`, `kg`, `l` ...
  final String unit;

  /// Server yozgan miqdor: `"2"`, `"1.5 kg"`. Ekranda shu ko'rsatiladi.
  final String quantityLabel;
  final List<KitchenModifier> modifiers;

  /// [KitchenLineStatus] qiymatlaridan biri (yoki backend qo'shgan yangisi).
  final String status;
  final DateTime? readyAt;

  bool get isReady => status == KitchenLineStatus.ready;

  /// "Osh (katta)" yoki "Osh".
  String get title {
    final String? variant = variantName?.trim();
    return variant == null || variant.isEmpty
        ? productName
        : '$productName ($variant)';
  }

  KitchenLine copyWith({String? status, DateTime? readyAt}) => KitchenLine(
    id: id,
    productName: productName,
    variantName: variantName,
    quantity: quantity,
    unit: unit,
    quantityLabel: quantityLabel,
    modifiers: modifiers,
    status: status ?? this.status,
    readyAt: readyAt ?? this.readyAt,
  );
}

/// Bitta buyurtmaning oshxona chiptasi. Mijoz ma'lumoti va narxlar yo'q.
class KitchenTicket {
  const KitchenTicket({
    required this.uuid,
    required this.status,
    required this.type,
    required this.lines,
    required this.waitingSeconds,
    required this.receivedAt,
    this.shortCode,
    this.channel = '',
    this.branchId,
    this.hallRef,
    this.tableRef,
    this.guestsCount,
    this.notes,
    this.createdAt,
    this.acceptedAt,
    this.preparingAt,
    this.readyAt,
  });

  /// [receivedAt] — javob qurilmaga kelgan payt (qurilma soati bilan): kutish vaqti `waiting_seconds` dan shu paytdan
  /// boshlab sanaladi, shuning uchun planshet soati serverdan farq qilsa ham taymer to'g'ri.
  factory KitchenTicket.fromJson(
    Map<String, Object?> json, {
    required DateTime receivedAt,
  }) => KitchenTicket(
    uuid: json['uuid']! as String,
    shortCode: json['short_code'] as String?,
    status: (json['status'] as String?) ?? '',
    type: (json['type'] as String?) ?? '',
    channel: (json['channel'] as String?) ?? '',
    branchId: (json['branch_id'] as num?)?.toInt(),
    hallRef: json['hall_ref'] as String?,
    tableRef: json['table_ref'] as String?,
    guestsCount: (json['guests_count'] as num?)?.toInt(),
    notes: json['notes'] as String?,
    lines: _list(json['items'], KitchenLine.fromJson),
    createdAt: _date(json['created_at']),
    acceptedAt: _date(json['accepted_at']),
    preparingAt: _date(json['preparing_at']),
    readyAt: _date(json['ready_at']),
    waitingSeconds: (json['waiting_seconds'] as num?)?.toInt() ?? 0,
    receivedAt: receivedAt,
  );

  final String uuid;
  final String? shortCode;

  /// Buyurtma holati: `accepted`, `preparing` (`include_ready=1` bilan `ready` ham).
  final String status;

  /// `delivery`, `pickup`, `dine_in`.
  final String type;

  /// `telegram`, `mobile`, `pos_hall`, `pos_takeaway`, `aggregator`.
  final String channel;
  final int? branchId;
  final String? hallRef;
  final String? tableRef;
  final int? guestsCount;

  /// Mijoz izohi (telefon raqamlari niqoblangan).
  final String? notes;
  final List<KitchenLine> lines;
  final DateTime? createdAt;
  final DateTime? acceptedAt;
  final DateTime? preparingAt;
  final DateTime? readyAt;

  /// Server hisoblagan kutish vaqti (qabul qilingandan, bo'lmasa yaratilgandan) javob paytida.
  final int waitingSeconds;
  final DateTime receivedAt;

  /// `#E101` (qisqa kod) yoki uuid boshi: Mini App va web panel bilan bir xil (`formatOrderCode`).
  String get code {
    final String? short = shortCode?.trim();
    if (short != null && short.isNotEmpty) return '#$short';
    return '#${uuid.substring(0, uuid.length < 8 ? uuid.length : 8).toUpperCase()}';
  }

  int get readyCount => lines.where((KitchenLine l) => l.isReady).length;

  bool get allLinesReady => lines.isNotEmpty && readyCount == lines.length;

  /// Oshxonada hali pishirilayotgan (server `accepted` yoki `preparing` deb yuborgan) chipta.
  bool get isCooking => status == 'accepted' || status == 'preparing';

  /// [now] paytidagi kutish vaqti. Tayyor bo'lgan buyurtmada server vaqti to'xtagan.
  Duration waitingAt(DateTime now) {
    final Duration server = Duration(seconds: waitingSeconds);
    if (!isCooking) return server;
    final Duration since = now.difference(receivedAt);
    return since.isNegative ? server : server + since;
  }

  KitchenTicket copyWith({List<KitchenLine>? lines, String? status}) =>
      KitchenTicket(
        uuid: uuid,
        shortCode: shortCode,
        status: status ?? this.status,
        type: type,
        channel: channel,
        branchId: branchId,
        hallRef: hallRef,
        tableRef: tableRef,
        guestsCount: guestsCount,
        notes: notes,
        lines: lines ?? this.lines,
        createdAt: createdAt,
        acceptedAt: acceptedAt,
        preparingAt: preparingAt,
        readyAt: readyAt,
        waitingSeconds: waitingSeconds,
        receivedAt: receivedAt,
      );
}

/// `GET /staff/kitchen/tickets` natijasi: eskisidan yangisiga, ko'pi bilan 200 ta.
class KitchenTicketList {
  const KitchenTicketList({required this.tickets, this.truncated = false});

  final List<KitchenTicket> tickets;

  /// 200 tadan ko'p: ro'yxat to'liq emas.
  final bool truncated;
}

List<T> _list<T>(Object? raw, T Function(Map<String, Object?>) parse) =>
    raw is List
    ? raw
          .whereType<Map<Object?, Object?>>()
          .map((Map<Object?, Object?> e) => parse(e.cast<String, Object?>()))
          .toList()
    : <T>[];

DateTime? _date(Object? raw) => raw is String ? DateTime.tryParse(raw) : null;
