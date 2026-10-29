import 'dart:async';

import 'package:pos_data/pos_data.dart';

import 'counter_format.dart';

/// P6-13: kassaning navbat raqamlari (docs/POS_SPEC.md 2.14). Raqam kassaning bugungi blokidan olinadi va qurilma
/// jurnaliga yoziladi (`SyncStore.takeQueueNumber`): internetsiz ham, ilova qayta ochilsa ham shu kunda qayta
/// berilmaydi. Server boshqa raqam bersa — u ham ishlatilgan deb yoziladi ([note]). Raqam kam qolsa aloqa borida keyingi
/// blok so'raladi ([topUp]).
///
/// Modul o'chiq, bootstrap `queue.numbers` yo'q yoki qurilma jurnali bo'lmasa — raqam olinmaydi: buyurtma raqamsiz
/// ochiladi va raqamni (bo'lsa) server beradi.
class CounterQueueNumbers {
  CounterQueueNumbers(this.data, {PosQueueRepository? repository})
    : repository = repository ?? PosQueueRepository(data.http);

  static final Expando<CounterQueueNumbers> _byData =
      Expando<CounterQueueNumbers>();

  /// Bitta terminal ([PosData]) uchun bitta nusxa (yangi olingan blok sahifa qayta ochilganda ham esda).
  static CounterQueueNumbers of(PosData data) =>
      _byData[data] ??= CounterQueueNumbers(data).._watchSync();

  /// Internetsiz ochilgan buyurtma serverga yetdi: server bergan raqam (`refs.queue_number`; `queue_number_taken`
  /// bo'lsa boshqasi) ham ishlatilgan deb yoziladi.
  void _watchSync() {
    data.offline?.synced.listen((List<SyncOperation> ops) {
      for (final SyncOperation op in ops) {
        final Object? n = op.refs['queue_number'];
        if (op.type == SyncOpType.orderOpen && n is int) unawaited(note(n));
      }
    });
  }

  final PosData data;
  final PosQueueRepository repository;

  PosQueueState? _fresh;
  Future<void>? _toppingUp;

  /// Oxirgi ma'lum bloklar: `POST /pos/queue/blocks` javobi yoki bootstrap.
  PosQueueState get state =>
      _fresh != null && _fresh!.businessDate == _bootState.businessDate
      ? _fresh!
      : _bootState;

  PosQueueState get _bootState =>
      data.session.bootstrap?.queue ?? const PosQueueState();

  SyncStore? get _store => data.offline?.store;

  /// Raqamlash yoqilgan (modul va server).
  bool get enabled =>
      data.session.queueNumbers && state.numbers && state.businessDate != null;

  /// Bugun qolgan raqamlar soni (jurnal yo'q bo'lsa `null`).
  int? get left {
    final SyncStore? s = _store;
    final String? date = state.businessDate;
    if (!enabled || s == null || date == null) return null;
    return s.queueNumbersLeft(date, state.blocks);
  }

  /// Keyingi raqam (diskka yozilgach); olinmasa `null`.
  Future<int?> take() async {
    final SyncStore? s = _store;
    final String? date = state.businessDate;
    if (!enabled || s == null || date == null) return null;
    final int? n = await s.takeQueueNumber(date, state.blocks);
    unawaited(topUp());
    return n;
  }

  /// Server buyurtmaga bergan raqam (u ham ishlatilgan: kassa undan pastga qaytmaydi).
  Future<void> note(int? number) async {
    final SyncStore? s = _store;
    final String? date = state.businessDate;
    if (number == null || s == null || date == null) return;
    await s.noteQueueNumber(date, number);
  }

  /// Raqam kam qolgan bo'lsa (aloqa borida) keyingi blok. Xato jim: keyingi buyurtmada yana uriniladi.
  Future<void> topUp() => _toppingUp ??= _topUp().whenComplete(() {
    _toppingUp = null;
  });

  Future<void> _topUp() async {
    final SyncStore? s = _store;
    if (!enabled || s == null) return;
    if (data.offline?.active ?? false) return;
    if (!state.runningLow(s)) return;
    try {
      _fresh = await repository.reserveBlocks();
    } on PosException {
      // Aloqa yo'q yoki modul o'chirildi: bloklar tugasa buyurtma raqamsiz ochiladi
    }
  }
}
