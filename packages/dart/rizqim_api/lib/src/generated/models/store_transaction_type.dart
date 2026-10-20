// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

@JsonEnum()
enum StoreTransactionType {
  @JsonValue('commission')
  commission('commission'),
  @JsonValue('topup')
  topup('topup'),
  @JsonValue('settlement')
  settlement('settlement'),
  @JsonValue('payout')
  payout('payout'),
  @JsonValue('refund')
  refund('refund'),
  @JsonValue('adjustment')
  adjustment('adjustment'),
  @JsonValue('subscription')
  subscription('subscription'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const StoreTransactionType(this.json);

  factory StoreTransactionType.fromJson(String json) =>
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
  static List<StoreTransactionType> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
