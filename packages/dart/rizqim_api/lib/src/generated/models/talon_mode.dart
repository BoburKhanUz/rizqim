// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// customer_accounts.talon_mode (P6-44), how a corporate account's people eat: - none: the company is charged as a whole; naming a member is optional (recorded when given);  - daily_limit: every charge names a member (the talon: their code, or their phone), at most their `daily_limit`    sum per business day;  - subscription (abonement): every charge names a member, at most their `meals_per_day` charges per business day; the    monthly statement counts the meals. A member's `daily_limit`, when set, is checked in this mode too.  "Talon" is the member's code (printed as a QR chit or on a badge), not a paper ticket that is used up: the limits are counted on the server, so a lost or copied chit can only be used within its owner's limits.
///
@JsonEnum()
enum TalonMode {
  @JsonValue('none')
  none('none'),
  @JsonValue('daily_limit')
  dailyLimit('daily_limit'),
  @JsonValue('subscription')
  subscription('subscription'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const TalonMode(this.json);

  factory TalonMode.fromJson(String json) =>
      values.firstWhere((e) => e.json == json, orElse: () => $unknown);

  final String? json;
  String toJson() {
    final value = json;
    if (value == null) {
      throw StateError(
        'Cannot convert enum value with null JSON representation to String. '
        'This usually happens for \$unknown or @JsonValue(null) entries.',
      );
    }
    return value as String;
  }

  @override
  String toString() => json?.toString() ?? super.toString();

  /// Returns all defined enum values excluding the $unknown value.
  static List<TalonMode> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
