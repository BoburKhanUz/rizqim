// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'products_missing_mxik.dart';

part 'data78.g.dart';

@JsonSerializable()
class Data78 {
  const Data78({
    required this.enabled,
    required this.tinSet,
    required this.taxRegime,
    required this.productsMissingMxik,
    required this.chargesMissingMxik,
  });

  factory Data78.fromJson(Map<String, Object?> json) => _$Data78FromJson(json);

  final bool enabled;
  @JsonKey(name: 'tin_set')
  final bool tinSet;
  @JsonKey(name: 'tax_regime')
  final String? taxRegime;
  @JsonKey(name: 'products_missing_mxik')
  final List<ProductsMissingMxik> productsMissingMxik;
  @JsonKey(name: 'charges_missing_mxik')
  final List<String> chargesMissingMxik;

  Map<String, Object?> toJson() => _$Data78ToJson(this);
}
