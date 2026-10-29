// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P6-22: what one split of the pool covers: a business day of the branch (Asia/Tashkent, from the branch's day start) or one till shift.
///
@JsonEnum()
enum TipPoolPeriod {
  @JsonValue('day')
  day('day'),
  @JsonValue('shift')
  shift('shift'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const TipPoolPeriod(this.json);

  factory TipPoolPeriod.fromJson(String json) =>
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
  static List<TipPoolPeriod> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
