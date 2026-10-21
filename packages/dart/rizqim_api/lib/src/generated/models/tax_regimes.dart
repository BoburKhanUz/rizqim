// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'tax_regimes.g.dart';

@JsonSerializable()
class TaxRegimes {
  const TaxRegimes({
    required this.value,
    required this.label,
    required this.vatRate,
  });

  factory TaxRegimes.fromJson(Map<String, Object?> json) =>
      _$TaxRegimesFromJson(json);

  final String value;
  final String label;
  @JsonKey(name: 'vat_rate')
  final String vatRate;

  Map<String, Object?> toJson() => _$TaxRegimesToJson(this);
}
