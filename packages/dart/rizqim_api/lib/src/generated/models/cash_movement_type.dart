// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// cash_movements.type: what moved money into or out of the drawer (POS_SPEC 2.3, research R21, L7, L10). The cashier records `cash_in`, `cash_out`, `wage` (to an employee) and `purchase` (bazaar shopping) by hand; the others are written by the shift, sale and refund flows themselves. Booking deposits and nasiya repayments taken in cash (P3-17), and cash deposit refunds, are read from their own tables by the shift report (ShiftReport), not copied here.
///
@JsonEnum()
enum CashMovementType {
  @JsonValue('opening')
  opening('opening'),
  @JsonValue('sale')
  sale('sale'),
  @JsonValue('refund')
  refund('refund'),
  @JsonValue('cash_in')
  cashIn('cash_in'),
  @JsonValue('cash_out')
  cashOut('cash_out'),
  @JsonValue('wage')
  wage('wage'),
  @JsonValue('purchase')
  purchase('purchase'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const CashMovementType(this.json);

  factory CashMovementType.fromJson(String json) =>
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
  static List<CashMovementType> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
