import 'dart:convert';

/// M5 S6-a (owner answer 3, docs/scale/M5_DESIGN.md §2 "App restart during an outage"): the last staff member who
/// entered a PIN on this till, kept so that after an app restart with no server the till can offer "Davom etish:
/// (name)" for at most [window] after that PIN login.
///
/// Only these four facts are kept: the user id, the name, the permissions the server gave at the PIN login, and when
/// the PIN was entered. Never the PIN, a PIN hash or a token: a continued staff member sends no staff token, the
/// till's operations go through the journal with the terminal token, and the server checks the staff member as it
/// always does (its own PIN-login record).
class StaffResumeRecord {
  const StaffResumeRecord({
    required this.userId,
    required this.userName,
    required this.permissions,
    required this.signedInAt,
  });

  /// How long after the PIN login the record may be used (owner decision: 12 h).
  static const Duration window = Duration(hours: 12);

  static const int version = 1;

  /// `null`: not a record of this version (a damaged or foreign value is never trusted).
  static StaffResumeRecord? tryParse(String raw) {
    try {
      final Object? decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      final Map<String, Object?> j = decoded.cast<String, Object?>();
      final Object? id = j['staff_id'];
      final Object? name = j['name'];
      final Object? permissions = j['permissions'];
      final Object? at = j['signed_in_at'];
      if (j['v'] != version ||
          id is! int ||
          name is! String ||
          permissions is! List ||
          at is! String) {
        return null;
      }
      return StaffResumeRecord(
        userId: id,
        userName: name,
        permissions: permissions.whereType<String>().toSet(),
        signedInAt: DateTime.parse(at).toUtc(),
      );
    } on Object {
      return null;
    }
  }

  final int userId;
  final String userName;
  final Set<String> permissions;

  /// When the PIN was entered on this till (the device clock, UTC).
  final DateTime signedInAt;

  /// The last moment the record may be used.
  DateTime get until => signedInAt.add(window);

  /// Usable at [now]: no later than [window] after the PIN login, and the PIN login is not in the future (a device
  /// clock set back before it: nothing about the record can be trusted).
  bool usableAt(DateTime now) =>
      !now.isBefore(signedInAt) && !now.isAfter(until);

  String encode() => jsonEncode(<String, Object?>{
    'v': version,
    'staff_id': userId,
    'name': userName,
    'permissions': (permissions.toList()..sort()),
    'signed_in_at': signedInAt.toUtc().toIso8601String(),
  });
}
