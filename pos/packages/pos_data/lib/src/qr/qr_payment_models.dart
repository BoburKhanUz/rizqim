import 'package:pos_core/pos_core.dart';

import '../bootstrap/bootstrap_models.dart';
import '../display/display_models.dart';
import '../json.dart';
import '../shift/shift_models.dart';

/// P6-41/P6-42: dinamik QR provayderi (`provider`): Click, Payme (do'konning o'z merchant hisobi) yoki UzQR (shlyuz).
enum PosQrProvider {
  click('click', 'Click'),
  payme('payme', 'Payme'),
  uzqr('uzqr', 'UzQR');

  const PosQrProvider(this.wire, this.label);

  final String wire;
  final String label;

  static PosQrProvider? fromWire(String? wire) {
    for (final PosQrProvider p in values) {
      if (p.wire == wire) return p;
    }
    return null;
  }
}

/// QR holati: `pending` — mijoz to'lashi mumkin; `paid` — provayder tasdiqladi, schyot `qr` to'lovini oldi;
/// `cancelled`, `expired`, `refunded`.
enum PosQrStatus {
  pending('pending'),
  paid('paid'),
  cancelled('cancelled'),
  expired('expired'),
  refunded('refunded'),
  unknown('');

  const PosQrStatus(this.wire);

  final String wire;

  static PosQrStatus fromWire(String? wire) {
    for (final PosQrStatus s in values) {
      if (s.wire == wire && s != unknown) return s;
    }
    return unknown;
  }

  /// Endi o'zgarmaydi (to'langan yoki tugagan).
  bool get isFinal => this != pending;
}

/// `POST /pos/bills/{uuid}/qr`, `GET /pos/qr/{uuid}` dagi QR so'rovi. [payload] — QR ichidagi matn (Click/Payme
/// to'lov havolasi yoki UzQR EMV qatori): schyotga chop etiladi va mijoz ekraniga [display] bilan yuboriladi.
class PosQrPayment {
  const PosQrPayment({
    required this.uuid,
    required this.billUuid,
    required this.provider,
    required this.amount,
    required this.status,
    required this.payload,
    this.orderUuid,
    this.providerLabel,
    this.displayLabel,
    this.expiresAt,
    this.held = false,
    this.paidAt,
    this.cancelledAt,
    this.cancelReason,
    this.paymentUuid,
    this.refundedAmount = PosMoney.zero,
    this.simulated = false,
  });

  factory PosQrPayment.fromJson(PosJson j) {
    final PosJson? display = j.objOrNull('display');
    return PosQrPayment(
      uuid: j.str('uuid'),
      billUuid: j.str('bill_uuid'),
      orderUuid: j.strOrNull('order_uuid'),
      provider: PosQrProvider.fromWire(j.strOrNull('provider')),
      providerLabel: j.strOrNull('provider_label'),
      amount: j.money('amount'),
      status: PosQrStatus.fromWire(j.strOrNull('status')),
      payload: j.str('payload'),
      displayLabel: display?.strOrNull('label'),
      expiresAt: j.dateOrNull('expires_at'),
      held: j.boolean('held'),
      paidAt: j.dateOrNull('paid_at'),
      cancelledAt: j.dateOrNull('cancelled_at'),
      cancelReason: j.strOrNull('cancel_reason'),
      paymentUuid: j.strOrNull('payment_uuid'),
      refundedAmount: j.money('refunded_amount'),
      simulated: j.boolean('simulated'),
    );
  }

  final String uuid;
  final String billUuid;
  final String? orderUuid;

  /// `null` — server yangi provayder qo'shgan (ilova eski).
  final PosQrProvider? provider;
  final String? providerLabel;
  final PosMoney amount;
  final PosQrStatus status;
  final String payload;
  final String? displayLabel;
  final DateTime? expiresAt;

  /// Provayder pulni olishni boshlagan (yangi QR so'ramang).
  final bool held;
  final DateTime? paidAt;
  final DateTime? cancelledAt;

  /// `cashier`, `replaced`, `bill_changed`, `shift_closed`, `payment_refused:<kod>`, `expired`, ...
  final String? cancelReason;
  final String? paymentUuid;
  final PosMoney refundedAmount;

  /// Sinov (sandbox) QR: demo "To'landi" tugmasi ([PosQrPaymentRepository.simulatePaid]).
  final bool simulated;

  /// Mijoz ekraniga (`PosDisplayPayment.qr`).
  PosDisplayQr get display => PosDisplayQr(
    payload: payload,
    label: displayLabel,
    provider: provider?.wire,
  );
}

/// `GET /pos/bootstrap` → `qr_payments` (P6-41): to'lov ekrani dinamik QR taklif qiladimi ([enabled] — modul va
/// kamida bitta ulangan provayder), qaysilari (asosiysi birinchi) va QR necha daqiqa amal qiladi. Internetsiz —
/// statik QR qo'lda tasdiqlanadi.
class PosQrSettings {
  const PosQrSettings({
    this.enabled = false,
    this.providers = const <PosQrProvider>[],
    this.defaultProvider,
    this.expiryMinutes = 5,
  });

  factory PosQrSettings.fromJson(PosJson j) {
    final List<PosQrProvider> providers = <PosQrProvider>[
      for (final String p
          in j['providers'] is List ? j.strings('providers') : const <String>[])
        ?PosQrProvider.fromWire(p),
    ];
    return PosQrSettings(
      enabled: j.boolean('enabled') && providers.isNotEmpty,
      providers: providers,
      defaultProvider: PosQrProvider.fromWire(j.strOrNull('default_provider')),
      expiryMinutes: j.intOrNull('expiry_minutes') ?? 5,
    );
  }

  final bool enabled;
  final List<PosQrProvider> providers;
  final PosQrProvider? defaultProvider;
  final int expiryMinutes;
}

/// Bootstrap'ning `qr_payments` qismi: [PosBootstrap.raw] dan.
extension PosBootstrapQrPayments on PosBootstrap {
  /// QR to'lov sozlamasi; eski server yoki o'qib bo'lmasa — o'chiq.
  PosQrSettings get qrPayments {
    final Object? block = raw['qr_payments'];
    if (block is! Map) return const PosQrSettings();
    try {
      return PosQrSettings.fromJson(PosJson(block.cast<String, Object?>()));
    } on FormatException {
      return const PosQrSettings();
    }
  }
}

/// X/Z dagi `qr_providers` qatori: `click`, `payme`, `uzqr` (provayder tasdiqlagan) yoki `manual` (statik QR, qo'lda).
class PosQrProviderTotal {
  const PosQrProviderTotal({
    required this.provider,
    this.count = 0,
    this.amount = PosMoney.zero,
  });

  final String provider;
  final int count;
  final PosMoney amount;
}

/// X/Z ning `qr_providers` qismi (P6-41): QR to'lovlar provayder bo'yicha (komissiya hisoblanmaydi).
extension PosShiftReportQrProviders on PosShiftReport {
  List<PosQrProviderTotal> get qrProviders {
    final Object? block = raw['qr_providers'];
    if (block is! List) return const <PosQrProviderTotal>[];
    return <PosQrProviderTotal>[
      for (final Object? e in block)
        if (e is Map)
          PosQrProviderTotal(
            provider: '${e['provider'] ?? ''}',
            count: PosJson(e.cast<String, Object?>()).intOrNull('count') ?? 0,
            amount: PosJson(e.cast<String, Object?>()).money('amount'),
          ),
    ];
  }
}
