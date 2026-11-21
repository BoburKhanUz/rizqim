import 'package:flutter/foundation.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// M5 S5: the day the "new version" banner was dismissed (`yyyy-MM-dd`, the phone's local day), so it comes at most
/// once a day. Kept in the phone's key-value store like the other small values of this app (the LAN keys); without a
/// store (tests) it lives for this run only. A read or write that fails is ignored: the banner just comes again.
class UpdateNoticePrefs extends ChangeNotifier {
  UpdateNoticePrefs(this._store);

  final SecureKeyValueStore? _store;

  static const String _key = 'waiter.update_dismissed_on';

  String? _dismissedOn;
  Future<void>? _loading;

  /// The day the banner was dismissed; `null` — not dismissed (or not read yet).
  String? get dismissedOn => _dismissedOn;

  /// Reads the saved day (once).
  Future<void> load() => _loading ??= _load();

  Future<void> _load() async {
    try {
      final String? saved = await _store?.read(_key);
      if (saved != null && _dismissedOn == null) {
        _dismissedOn = saved;
        notifyListeners();
      }
    } on Object {
      // Unreadable: the banner is shown
    }
  }

  Future<void> dismiss(String day) async {
    _dismissedOn = day;
    notifyListeners();
    try {
      await _store?.write(_key, day);
    } on Object {
      // Not saved: hidden for this run
    }
  }
}
