// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// Where an order line is in the kitchen (order_items.kitchen_status, P2-11): `pending` until a cook bumps it on the kitchen display, then `ready`. docs/POS_SPEC.md §10.3 reserves more values for the POS phase (sent, served, void); they are added here when the POS starts sending lines to the kitchen.
///
@JsonEnum()
enum KitchenItemStatus {
  @JsonValue('pending')
  pending('pending'),
  @JsonValue('ready')
  ready('ready'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const KitchenItemStatus(this.json);

  factory KitchenItemStatus.fromJson(String json) =>
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
  static List<KitchenItemStatus> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
