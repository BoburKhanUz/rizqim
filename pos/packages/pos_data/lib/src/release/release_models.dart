/// M5 S5: what `GET /v1/terminal` says about this terminal's app version (`data.app_release`).
///
/// Only a notice. Nothing here stops a shift, an order, a payment, a receipt, a refund or the offline queue.
enum PosReleaseState {
  /// Up to date (or no newer build known).
  ok('ok'),

  /// A newer build exists: a quiet, dismissible notice.
  updateAvailable('update_available'),

  /// Below the minimum and past `minimum_from`: a permanent red notice (still never blocking).
  updateRequired('update_required');

  const PosReleaseState(this.wire);

  final String wire;

  /// An unknown word from a newer server reads as [ok]: no notice rather than a wrong one.
  static PosReleaseState of(Object? wire) {
    for (final PosReleaseState s in PosReleaseState.values) {
      if (s.wire == wire) return s;
    }
    return PosReleaseState.ok;
  }
}

/// `data.app_release` as the server gives it.
class PosAppRelease {
  const PosAppRelease({
    required this.state,
    required this.latest,
    required this.url,
    this.current,
    this.minimum,
    this.notes,
    this.minimumFrom,
  });

  /// `null`: not an object (no notice).
  static PosAppRelease? tryParse(Object? json) {
    if (json is! Map) return null;
    final Map<Object?, Object?> j = json;
    String? text(String key) {
      final Object? v = j[key];
      return v is String && v.isNotEmpty ? v : null;
    }

    final String? latest = text('latest');
    final String? url = text('url');
    if (latest == null || url == null) return null;
    return PosAppRelease(
      state: PosReleaseState.of(j['state']),
      current: text('current'),
      latest: latest,
      minimum: text('minimum'),
      url: url,
      notes: text('notes'),
      minimumFrom: DateTime.tryParse(text('minimum_from') ?? ''),
    );
  }

  final PosReleaseState state;

  /// The version this terminal reported (`1.4.0+7`).
  final String? current;
  final String latest;

  /// `null`: no minimum declared.
  final String? minimum;

  /// Download link of the latest build.
  final String url;

  /// Uzbek release notes.
  final String? notes;
  final DateTime? minimumFrom;

  bool get isRequired => state == PosReleaseState.updateRequired;

  @override
  bool operator ==(Object other) =>
      other is PosAppRelease &&
      other.state == state &&
      other.current == current &&
      other.latest == latest &&
      other.minimum == minimum &&
      other.url == url &&
      other.notes == notes &&
      other.minimumFrom == minimumFrom;

  @override
  int get hashCode =>
      Object.hash(state, current, latest, minimum, url, notes, minimumFrom);
}
