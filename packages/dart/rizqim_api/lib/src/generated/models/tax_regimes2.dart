// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'tax_regimes2.g.dart';

@JsonSerializable()
class TaxRegimes2 {
  const TaxRegimes2({
    required this.value,
    required this.label,
    required this.vatRate,
  });

  factory TaxRegimes2.fromJson(Map<String, Object?> json) =>
      _$TaxRegimes2FromJson(json);

  final String value;
  final String label;
  @JsonKey(name: 'vat_rate')
  final String vatRate;

  Map<String, Object?> toJson() => _$TaxRegimes2ToJson(this);
}
