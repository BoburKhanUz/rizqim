// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tax_regime.dart';
import 'fiscal_settings.dart';
import 'tax_regimes.dart';

part 'data81.g.dart';

@JsonSerializable()
class Data81 {
  const Data81({
    required this.tin,
    required this.taxRegime,
    required this.fiscalSettings,
    required this.drivers,
    required this.taxRegimes,
  });

  factory Data81.fromJson(Map<String, Object?> json) => _$Data81FromJson(json);

  final String? tin;
  @JsonKey(name: 'tax_regime')
  final TaxRegime? taxRegime;
  @JsonKey(name: 'fiscal_settings')
  final FiscalSettings fiscalSettings;
  final List<String> drivers;
  @JsonKey(name: 'tax_regimes')
  final List<TaxRegimes> taxRegimes;

  Map<String, Object?> toJson() => _$Data81ToJson(this);
}
