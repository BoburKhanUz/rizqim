import 'dart:async';

/// Server bilan aloqa holati, so'rovlar natijasidan: javob keldi — [online], javob kelmadi (tarmoq xatosi) —
/// [offline]. Yuqori paneldagi "Aloqa yo'q" belgisi va sinxronlash nishoni shundan.
///
/// [pendingOperations] — oflayn navbatda kutayotgan amallar soni. Hozir doim 0: internetsiz ishlash (navbat va
/// sinxronlash) 3-to'lqinda (P3-31) shu yerga ulanadi.
class PosConnectivity {
  PosConnectivity({DateTime Function()? clock})
    : _clock = clock ?? DateTime.now;

  final DateTime Function() _clock;
  final StreamController<PosConnectivityState> _changes =
      StreamController<PosConnectivityState>.broadcast();

  PosConnectivityState _state = const PosConnectivityState();

  PosConnectivityState get state => _state;

  Stream<PosConnectivityState> get changes => _changes.stream;

  /// Javob keldi (xato bo'lsa ham: server ishlayapti).
  void reportReachable() {
    final DateTime now = _clock();
    _set(_state.copyWith(online: true, lastContactAt: now, lastError: null));
  }

  /// Javob kelmadi.
  void reportFailure(Object error) {
    _set(_state.copyWith(online: false, lastError: error));
  }

  /// Oflayn navbatdagi amallar soni (P3-31).
  void setPendingOperations(int count) {
    _set(_state.copyWith(pendingOperations: count));
  }

  void _set(PosConnectivityState next) {
    final bool changed =
        next.online != _state.online ||
        next.pendingOperations != _state.pendingOperations ||
        next.lastContactAt != _state.lastContactAt;
    _state = next;
    if (changed && !_changes.isClosed) _changes.add(next);
  }

  Future<void> dispose() => _changes.close();
}

class PosConnectivityState {
  const PosConnectivityState({
    this.online = true,
    this.lastContactAt,
    this.lastError,
    this.pendingOperations = 0,
  });

  /// Oxirgi so'rovga javob keldi (hali so'rov bo'lmagan bo'lsa ham `true`).
  final bool online;

  /// Oxirgi marta server javob bergan vaqt.
  final DateTime? lastContactAt;
  final Object? lastError;

  /// Oflayn navbatdagi amallar (P3-31).
  final int pendingOperations;

  PosConnectivityState copyWith({
    bool? online,
    DateTime? lastContactAt,
    Object? lastError = _keep,
    int? pendingOperations,
  }) => PosConnectivityState(
    online: online ?? this.online,
    lastContactAt: lastContactAt ?? this.lastContactAt,
    lastError: identical(lastError, _keep) ? this.lastError : lastError,
    pendingOperations: pendingOperations ?? this.pendingOperations,
  );

  static const Object _keep = Object();
}
