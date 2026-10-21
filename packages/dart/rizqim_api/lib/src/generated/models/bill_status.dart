// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// order_bills.status: `open` when made, `printed` once handed to the guest (the order is locked from then on), `paid` when its payments cover it AND its sale receipt is issued, `void` when the order was billed again or cancelled.
///
@JsonEnum()
enum BillStatus {
  @JsonValue('open')
  open('open'),
  @JsonValue('printed')
  printed('printed'),
  @JsonValue('paid')
  paid('paid'),

  /// The name has been replaced because it contains a keyword. Original name: `void`.
  @JsonValue('void')
  valueVoid('void'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const BillStatus(this.json);

  factory BillStatus.fromJson(String json) =>
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
  static List<BillStatus> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
