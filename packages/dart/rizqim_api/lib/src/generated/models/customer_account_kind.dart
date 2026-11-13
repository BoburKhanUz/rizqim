// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// customer_accounts.kind (P6-44): `customer` is nasiya as in P3-17 (a person or a group; a charge at the till always needs customer_accounts.manage or a manager's approval); `corporate` is a company whose staff eat on its account (members with talons, daily limits or meals per day, a monthly limit and statement; a charge needs approval only when the owner switched `charge_approval` on).
///
@JsonEnum()
enum CustomerAccountKind {
  @JsonValue('customer')
  customer('customer'),
  @JsonValue('corporate')
  corporate('corporate'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const CustomerAccountKind(this.json);

  factory CustomerAccountKind.fromJson(String json) =>
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
  static List<CustomerAccountKind> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
