// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tax_regime.dart';
import 'fiscal_settings3.dart';

part 'v1_owner_fiscal_settings_request_body.g.dart';

@JsonSerializable()
class V1OwnerFiscalSettingsRequestBody {
  const V1OwnerFiscalSettingsRequestBody({
    this.tin,
    this.taxRegime,
    this.fiscalSettings,
  });

  factory V1OwnerFiscalSettingsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerFiscalSettingsRequestBodyFromJson(json);

  /// STIR: 9 ta raqam (null — o'chirish).
  final String? tin;

  /// vat | vat_simplified | turnover.
  @JsonKey(name: 'tax_regime')
  final TaxRegime? taxRegime;

  /// Fiskal sozlamalar (obyekt, qisman).
  @JsonKey(name: 'fiscal_settings')
  final FiscalSettings3? fiscalSettings;

  Map<String, Object?> toJson() =>
      _$V1OwnerFiscalSettingsRequestBodyToJson(this);
}
