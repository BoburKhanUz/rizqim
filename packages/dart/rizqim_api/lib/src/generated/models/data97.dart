// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tax_regime.dart';
import 'fiscal_settings2.dart';
import 'tax_regimes2.dart';

part 'data97.g.dart';

@JsonSerializable()
class Data97 {
  const Data97({
    required this.tin,
    required this.taxRegime,
    required this.fiscalSettings,
    required this.drivers,
    required this.taxRegimes,
  });

  factory Data97.fromJson(Map<String, Object?> json) => _$Data97FromJson(json);

  final String? tin;
  @JsonKey(name: 'tax_regime')
  final TaxRegime? taxRegime;
  @JsonKey(name: 'fiscal_settings')
  final FiscalSettings2 fiscalSettings;
  final List<String> drivers;
  @JsonKey(name: 'tax_regimes')
  final List<TaxRegimes2> taxRegimes;

  Map<String, Object?> toJson() => _$Data97ToJson(this);
}
