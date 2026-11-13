import 'fake_pos_state.dart';

/// P6-43: soxta sovg'a sertifikati yo'llari ([FakePosState.extraRoutes] ga ulanadi, avvalgi yo'llar saqlanadi):
/// `POST /pos/vouchers/sell`, `GET /pos/vouchers/{code}`, va schyot to'lovidagi `voucher` tender — kod, holat va
/// qoldiq tekshiriladi, qoldiq kamayadi, keyin to'lovni [FakeCashierBackend] yozadi. [moduleOff] — sotish 403
/// `module_disabled`. Promo-kod yo'llari yo'q (buyurtma chegirmasi — backend testlarida).
class FakeVoucherBackend {
  FakeVoucherBackend(this.state) {
    _next = state.extraRoutes;
    state.extraRoutes = handle;
  }

  final FakePosState state;
  FakeResponse? Function(FakeRequest request)? _next;

  /// code → sertifikat (serverning ko'rinishi).
  final Map<String, Map<String, Object?>> vouchers =
      <String, Map<String, Object?>>{};
  bool moduleOff = false;
  int _seq = 0;

  static FakeResponse _ok(Object? data, {int status = 200}) => FakeResponse(
    status,
    <String, Object?>{'success': true, 'message': '', 'data': data},
  );

  static FakeResponse _fail(
    int status,
    String reason,
    String message, [
    Map<String, Object?> extra = const <String, Object?>{},
  ]) => FakeResponse(status, <String, Object?>{
    'success': false,
    'message': message,
    'data': <String, Object?>{'reason': reason, ...extra},
  });

  static String? normalize(Object? code) {
    final String chars = '${code ?? ''}'.toUpperCase().replaceAll(
      RegExp(r'[\s\-_]'),
      '',
    );
    if (chars.length != 12) return null;
    return '${chars.substring(0, 4)}-${chars.substring(4, 8)}-${chars.substring(8)}';
  }

  static int _t(Object? amount) =>
      (double.parse('${amount ?? 0}') * 100).round();
  static String _m(int tiyin) =>
      '${tiyin ~/ 100}.${(tiyin % 100).toString().padLeft(2, '0')}';

  /// Sotilgan sertifikat (testlar to'g'ridan-to'g'ri ham qo'sha oladi).
  Map<String, Object?> add({
    required String faceValue,
    String? code,
    String tender = 'cash',
    String status = 'active',
  }) {
    _seq++;
    const List<String> tails = <String>['ACDE', 'FGHJ', 'KMNP', 'QRTU', 'VWXY'];
    final String c = code ?? 'KQMA-CDEF-${tails[(_seq - 1) % tails.length]}';
    final Map<String, Object?> v = <String, Object?>{
      'uuid': '00000000-0000-4000-9000-${_seq.toString().padLeft(12, '0')}',
      'code': c,
      'kind': 'gift',
      'face_value': faceValue,
      'balance': faceValue,
      'currency': 'UZS',
      'valid_from': '2026-10-22',
      'valid_until': '2027-10-22',
      'status': status,
      'status_label': status,
      'branch': <String, Object?>{'id': 1, 'name': 'Filial'},
      'sale': <String, Object?>{
        'tender': tender,
        'card_type': null,
        'transaction_id': null,
        'reference': null,
        'shift_id': 1,
        'sold_at': '2026-10-22T13:00:00Z',
        'sold_by': <String, Object?>{'id': 3, 'name': 'Kassir'},
      },
      'buyer': <String, Object?>{'name': null, 'phone': null},
      'note': null,
      'fiscal': <String, Object?>{'receipt_uuid': null, 'status': 'issued'},
      'blocked': null,
      'print': <String, Object?>{
        'store': 'Choyxona',
        'code': c,
        'qr': c,
        'value': faceValue,
        'valid_from': '2026-10-22',
        'valid_until': '2027-10-22',
      },
      'created_at': '2026-10-22T13:00:00Z',
    };
    vouchers[c] = v;
    return v;
  }

  FakeResponse? handle(FakeRequest r) {
    final String p = r.path;
    if (p == '/v1/pos/vouchers/sell' && r.method == 'POST') {
      if (moduleOff) {
        return _fail(
          403,
          'module_disabled',
          "Bu filialda sertifikat moduli o'chiq.",
        );
      }
      if (state.shift == null) {
        return _fail(
          409,
          'shift_not_open',
          "Ochiq smena yo'q. Avval smenani oching.",
        );
      }
      final Map<String, Object?> v = add(
        faceValue: _m(_t(r.data['face_value'])),
        tender: '${r.data['tender'] ?? 'cash'}',
      );
      (v['buyer']! as Map<String, Object?>)['name'] = r.data['buyer_name'];
      return _ok(v, status: 201);
    }
    final RegExpMatch? lookup = RegExp(r'^/v1/pos/vouchers/([^/]+)$')
        .firstMatch(p);
    if (lookup != null && r.method == 'GET') {
      final Map<String, Object?>? v =
          vouchers[normalize(Uri.decodeComponent(lookup.group(1)!))];
      if (v == null) {
        return _fail(404, 'voucher_not_found', 'Sertifikat topilmadi.');
      }
      return _ok(<String, Object?>{'voucher': v, 'entries': <Object?>[]});
    }
    if (RegExp(r'^/v1/pos/bills/[^/]+/payments$').hasMatch(p) &&
        r.method == 'POST') {
      final List<Map<String, Object?>> spend = <Map<String, Object?>>[
        for (final Object? t
            in (r.data['tenders'] as List<Object?>? ?? <Object?>[]))
          if (t is Map && t['tender'] == 'voucher') t.cast<String, Object?>(),
      ];
      for (final Map<String, Object?> t in spend) {
        final Map<String, Object?>? v = vouchers[normalize(t['voucher_code'])];
        if (v == null) {
          return _fail(404, 'voucher_not_found', 'Sertifikat topilmadi.');
        }
        if (v['status'] == 'blocked') {
          return _fail(409, 'voucher_blocked', 'Sertifikat bloklangan.');
        }
        if (v['status'] == 'expired') {
          return _fail(409, 'voucher_expired', "Sertifikat muddati o'tgan.");
        }
        if (_t(t['amount']) > _t(v['balance'])) {
          return _fail(
            409,
            'voucher_insufficient',
            "Sertifikatda yetarli mablag' yo'q: qoldiq ${v['balance']} so'm.",
            <String, Object?>{'balance': v['balance']},
          );
        }
      }
      for (final Map<String, Object?> t in spend) {
        final Map<String, Object?> v = vouchers[normalize(t['voucher_code'])]!;
        final int left = _t(v['balance']) - _t(t['amount']);
        v['balance'] = _m(left);
        if (left == 0) v['status'] = 'used';
      }
    }
    return _next?.call(r);
  }
}
