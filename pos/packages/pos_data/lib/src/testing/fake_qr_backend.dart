import 'fake_pos_state.dart';

/// P6-41/P6-42: soxta QR to'lov yo'llari ([FakePosState.extraRoutes] ga ulanadi, avvalgi yo'llar saqlanadi):
/// `POST /pos/bills/{uuid}/qr`, `GET /pos/bills/{uuid}/qr`, `GET|DELETE /pos/qr/{uuid}`, `POST .../simulate-paid`.
/// [confirm] — provayder tasdig'i (`qr.paid` hodisasi bilan); [notConfigured] dagi provayder — 409
/// `provider_not_configured`. Schyot to'lovini o'zi qilmaydi: testlar buni o'zi tekshiradi.
class FakeQrBackend {
  FakeQrBackend(this.state) {
    _next = state.extraRoutes;
    state.extraRoutes = handle;
  }

  final FakePosState state;
  FakeResponse? Function(FakeRequest request)? _next;

  /// uuid → QR (serverning ko'rinishi).
  final Map<String, Map<String, Object?>> qrs =
      <String, Map<String, Object?>>{};
  final Set<String> notConfigured = <String>{};
  int _seq = 0;

  /// Provayder tasdiqladi: `paid` va `qr.paid`.
  void confirm(String uuid) {
    final Map<String, Object?> qr = qrs[uuid]!;
    qr['status'] = 'paid';
    qr['paid_at'] = '2026-10-22T13:05:00Z';
    qr['payment_uuid'] = 'pay-$uuid';
    state.emit('qr.paid', <String, Object?>{
      'qr': <String, Object?>{
        'uuid': uuid,
        'bill_uuid': qr['bill_uuid'],
        'order_uuid': null,
        'provider': qr['provider'],
        'status': 'paid',
      },
      'changed_at': '2026-10-22T13:05:00Z',
    });
  }

  static FakeResponse _ok(Object? data, {int status = 200}) => FakeResponse(
    status,
    <String, Object?>{'success': true, 'message': '', 'data': data},
  );

  static FakeResponse _fail(int status, String reason, String message) =>
      FakeResponse(status, <String, Object?>{
        'success': false,
        'message': message,
        'data': <String, Object?>{'reason': reason},
      });

  FakeResponse? handle(FakeRequest r) {
    final String p = r.path;
    RegExpMatch? x;
    if ((x = RegExp(r'^/v1/pos/bills/([^/]+)/qr$').firstMatch(p)) != null) {
      final String bill = x!.group(1)!;
      if (r.method == 'GET') {
        return _ok(<String, Object?>{
          'qrs': qrs.values
              .where((Map<String, Object?> q) => q['bill_uuid'] == bill)
              .toList()
              .reversed
              .toList(),
        });
      }
      final String provider = '${r.data['provider'] ?? 'click'}';
      if (notConfigured.contains(provider)) {
        return _fail(
          409,
          'provider_not_configured',
          "$provider do'konning o'z merchant hisobiga ulanmagan.",
        );
      }
      for (final Map<String, Object?> old in qrs.values) {
        if (old['bill_uuid'] == bill && old['status'] == 'pending') {
          old['status'] = 'cancelled';
          old['cancel_reason'] = 'replaced';
        }
      }
      final String uuid =
          '00000000-0000-4000-8000-${(++_seq).toString().padLeft(12, '0')}';
      final String amount = '${r.data['amount'] ?? '143000.00'}';
      final String payload = provider == 'uzqr'
          ? '000201010212'
          : 'https://qr-sandbox.invalid/$provider?account=pos_qr_$uuid';
      qrs[uuid] = <String, Object?>{
        'uuid': uuid,
        'bill_uuid': bill,
        'order_uuid': null,
        'provider': provider,
        'provider_label': provider == 'uzqr'
            ? 'UzQR'
            : '${provider[0].toUpperCase()}${provider.substring(1)}',
        'amount': amount,
        'status': 'pending',
        'payload': payload,
        'display': <String, Object?>{
          'payload': payload,
          'label': "to'lang: $amount so'm",
          'provider': provider,
        },
        'expires_at': '2026-10-22T13:05:00Z',
        'held': false,
        'paid_at': null,
        'cancelled_at': null,
        'cancel_reason': null,
        'payment_uuid': null,
        'refunded_amount': '0.00',
        'simulated': true,
        'created_at': '2026-10-22T13:00:00Z',
      };
      return _ok(<String, Object?>{'qr': qrs[uuid]}, status: 201);
    }
    if ((x = RegExp(r'^/v1/pos/qr/([^/]+)(/simulate-paid)?$').firstMatch(p)) !=
        null) {
      final Map<String, Object?>? qr = qrs[x!.group(1)];
      if (qr == null) return _fail(404, 'not_found', 'QR topilmadi.');
      if (x.group(2) != null) {
        confirm(x.group(1)!);
      } else if (r.method == 'DELETE') {
        if (qr['status'] == 'paid') {
          return _fail(409, 'qr_already_paid', "Bu QR allaqachon to'langan.");
        }
        if (qr['status'] == 'pending') {
          qr['status'] = 'cancelled';
          // The reason the till gave (`?reason=`), `cashier` by default
          qr['cancel_reason'] = r.query['reason'] ?? 'cashier';
        }
      }
      return _ok(<String, Object?>{'qr': qr});
    }
    return _next?.call(r);
  }
}
