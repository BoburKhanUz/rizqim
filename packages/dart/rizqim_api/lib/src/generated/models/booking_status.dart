// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// A booking's life (research report §4): requested → confirmed → arrived (the order is open) → completed (the order is closed); requested or confirmed → canceled; confirmed → no_show; requested → arrived for a guest who came before the request was confirmed. A taken deposit does not change the status: it is the booking's `deposit_total` (the report's "avans olindi" is confirmed + a deposit).
///
@JsonEnum()
enum BookingStatus {
  @JsonValue('requested')
  requested('requested'),
  @JsonValue('confirmed')
  confirmed('confirmed'),
  @JsonValue('arrived')
  arrived('arrived'),
  @JsonValue('completed')
  completed('completed'),
  @JsonValue('canceled')
  canceled('canceled'),
  @JsonValue('no_show')
  noShow('no_show'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const BookingStatus(this.json);

  factory BookingStatus.fromJson(String json) =>
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
  static List<BookingStatus> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
