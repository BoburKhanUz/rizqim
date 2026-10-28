// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P4-21: how often a subscription pays its base fee. Monthly: each month is billed when it ends. Yearly: 12 billing months are billed in advance when the term starts, for config('billing.yearly_months_charged') months' price; extra devices are still billed monthly when each month ends (docs/BILLING.md, "Yillik davr").
///
@JsonEnum()
enum BillingPeriod {
  @JsonValue('monthly')
  monthly('monthly'),
  @JsonValue('yearly')
  yearly('yearly'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const BillingPeriod(this.json);

  factory BillingPeriod.fromJson(String json) =>
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
  static List<BillingPeriod> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
