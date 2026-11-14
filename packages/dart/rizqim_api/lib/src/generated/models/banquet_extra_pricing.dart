// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// How an extra of the catalogue is priced (P6-52): once per event (tort, dekor, an extra hour) or per billed guest.
///
@JsonEnum()
enum BanquetExtraPricing {
  @JsonValue('per_event')
  perEvent('per_event'),
  @JsonValue('per_person')
  perPerson('per_person'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const BanquetExtraPricing(this.json);

  factory BanquetExtraPricing.fromJson(String json) =>
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
  static List<BanquetExtraPricing> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
