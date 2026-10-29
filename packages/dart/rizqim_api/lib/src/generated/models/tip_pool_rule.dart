// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P6-22: how a tip pool is split among the people taking part (TipPoolDistributor): `equal` — the same to each; `hours` — by minutes worked in the period; `sales` — by the paid bills of their orders (orders.waiter_id) in the period; `role_weights` — by the weight the owner gave their role.
///
@JsonEnum()
enum TipPoolRule {
  @JsonValue('equal')
  equal('equal'),
  @JsonValue('hours')
  hours('hours'),
  @JsonValue('sales')
  sales('sales'),
  @JsonValue('role_weights')
  roleWeights('role_weights'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const TipPoolRule(this.json);

  factory TipPoolRule.fromJson(String json) =>
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
  static List<TipPoolRule> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
