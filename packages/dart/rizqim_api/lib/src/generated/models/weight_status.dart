// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P6-25 (B3): where a "weight later" line is (order_items.weight_status; null: an ordinary line sold by the quantity typed). `awaiting`: the dish was chosen, its quantity is the estimate and no bill is made or printed while it waits; `weighed`: the real weight was entered (who and when are on the line), the price follows it.
///
@JsonEnum()
enum WeightStatus {
  @JsonValue('awaiting')
  awaiting('awaiting'),
  @JsonValue('weighed')
  weighed('weighed'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const WeightStatus(this.json);

  factory WeightStatus.fromJson(String json) =>
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
  static List<WeightStatus> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
