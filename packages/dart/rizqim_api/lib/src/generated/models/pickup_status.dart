// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P6-13 (A2): where a counter order with a queue number is for the customer waiting for it (orders.pickup_status, PickupQueue): `preparing` (the kitchen has it, the number shows under "Tayyorlanmoqda"), `ready` (called: the number shows under "Tayyor" and is announced), `handed` (the customer took it). Null: the order is not in the pickup queue (a table order, a delivery, a POS counter order before its lines went to the kitchen, an online pickup order of a branch without `queue_calling`). A POS counter order is tracked in every format (its kitchen lines outlive a payment taken first); only where `queue_calling` is on is it shown on the board and announced.
///
@JsonEnum()
enum PickupStatus {
  @JsonValue('preparing')
  preparing('preparing'),
  @JsonValue('ready')
  ready('ready'),
  @JsonValue('handed')
  handed('handed'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const PickupStatus(this.json);

  factory PickupStatus.fromJson(String json) =>
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
  static List<PickupStatus> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
