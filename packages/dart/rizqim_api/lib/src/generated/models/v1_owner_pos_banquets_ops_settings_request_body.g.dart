// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_banquets_ops_settings_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosBanquetsOpsSettingsRequestBody
_$V1OwnerPosBanquetsOpsSettingsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerPosBanquetsOpsSettingsRequestBody(
      osh: Osh3.fromJson(json['osh'] as Map<String, dynamic>),
      version: (json['version'] as num?)?.toInt(),
      reservePercent: json['reserve_percent'] as num?,
      portionWeights: (json['portion_weights'] as List<dynamic>?)
          ?.map((e) => PortionWeights3.fromJson(e as Map<String, dynamic>))
          .toList(),
      packageMxik: json['package_mxik'] as String?,
      packagePackageCode: json['package_package_code'] as String?,
      hallMxik: json['hall_mxik'] as String?,
      hallPackageCode: json['hall_package_code'] as String?,
      extraMxik: json['extra_mxik'] as String?,
      extraPackageCode: json['extra_package_code'] as String?,
      contractTemplate: json['contract_template'] as String?,
    );

Map<String, dynamic> _$V1OwnerPosBanquetsOpsSettingsRequestBodyToJson(
  V1OwnerPosBanquetsOpsSettingsRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'reserve_percent': instance.reservePercent,
  'osh': instance.osh,
  'portion_weights': instance.portionWeights,
  'package_mxik': instance.packageMxik,
  'package_package_code': instance.packagePackageCode,
  'hall_mxik': instance.hallMxik,
  'hall_package_code': instance.hallPackageCode,
  'extra_mxik': instance.extraMxik,
  'extra_package_code': instance.extraPackageCode,
  'contract_template': instance.contractTemplate,
};
