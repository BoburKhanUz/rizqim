// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// A deposit: `taken` (an ADVANCE receipt was issued; part of it may already be offset, see applied_amount), `applied` (fully offset against the order's bills), `refunded` (what was left went back to the guest) or `retained` (what was left was kept after a cancellation or no-show, with a manager's approval).
///
@JsonEnum()
enum BookingPaymentStatus {
  @JsonValue('taken')
  taken('taken'),
  @JsonValue('applied')
  applied('applied'),
  @JsonValue('refunded')
  refunded('refunded'),
  @JsonValue('retained')
  retained('retained'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const BookingPaymentStatus(this.json);

  factory BookingPaymentStatus.fromJson(String json) =>
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
  static List<BookingPaymentStatus> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
