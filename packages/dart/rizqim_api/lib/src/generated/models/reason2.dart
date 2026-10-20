// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

@JsonEnum()
enum Reason2 {
  @JsonValue('customer_request')
  customerRequest('customer_request'),
  @JsonValue('no_show')
  noShow('no_show'),
  @JsonValue('out_of_stock')
  outOfStock('out_of_stock'),
  @JsonValue('store_closed')
  storeClosed('store_closed'),
  @JsonValue('courier_unavailable')
  courierUnavailable('courier_unavailable'),
  @JsonValue('other')
  other('other'),

  /// The name has been replaced because it contains a keyword. Original name: `null`.
  @JsonValue(null)
  valueNull(null),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const Reason2(this.json);

  factory Reason2.fromJson(String? json) =>
      values.firstWhere((e) => e.json == json, orElse: () => $unknown);

  final String? json;
  String? toJson() {
    final value = json;
    if (value == null) {
      throw StateError(
        'Cannot convert enum value with null JSON representation to String?. '
        'This usually happens for \$unknown or @JsonValue(null) entries.',
      );
    }
    return value as String?;
  }

  @override
  String toString() => json?.toString() ?? super.toString();

  /// Returns all defined enum values excluding the $unknown value.
  static List<Reason2> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
