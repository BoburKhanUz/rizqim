// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P6-22: whom a branch's tips go to (pos_tip_settings.mode, pos_tips.mode): `personal` — the order's waiter, or the one picked at payment; `pool` — a shared pot split by the branch's rule (TipPoolDistributor).
///
@JsonEnum()
enum TipMode {
  @JsonValue('personal')
  personal('personal'),
  @JsonValue('pool')
  pool('pool'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const TipMode(this.json);

  factory TipMode.fromJson(String json) =>
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
  static List<TipMode> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
