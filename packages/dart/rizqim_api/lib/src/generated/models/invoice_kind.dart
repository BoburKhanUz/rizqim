// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P4-21: what a subscription invoice bills. monthly: a month that ended (base fee, extra devices, discount; for a yearly subscription only its extra devices); yearly: a yearly term in advance; upgrade: the prorated difference of an immediate upgrade of a yearly subscription.
///
@JsonEnum()
enum InvoiceKind {
  @JsonValue('monthly')
  monthly('monthly'),
  @JsonValue('yearly')
  yearly('yearly'),
  @JsonValue('upgrade')
  upgrade('upgrade'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const InvoiceKind(this.json);

  factory InvoiceKind.fromJson(String json) =>
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
  static List<InvoiceKind> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
