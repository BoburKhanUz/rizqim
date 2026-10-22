// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_fiscal_settings_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerFiscalSettingsRequestBody _$V1OwnerFiscalSettingsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerFiscalSettingsRequestBody(
  tin: json['tin'] as String?,
  taxRegime: json['tax_regime'] == null
      ? null
      : TaxRegime.fromJson(json['tax_regime'] as String?),
  fiscalSettings: json['fiscal_settings'] == null
      ? null
      : FiscalSettings3.fromJson(
          json['fiscal_settings'] as Map<String, dynamic>,
        ),
);

Map<String, dynamic> _$V1OwnerFiscalSettingsRequestBodyToJson(
  V1OwnerFiscalSettingsRequestBody instance,
) => <String, dynamic>{
  'tin': instance.tin,
  'tax_regime': instance.taxRegime,
  'fiscal_settings': instance.fiscalSettings,
};
