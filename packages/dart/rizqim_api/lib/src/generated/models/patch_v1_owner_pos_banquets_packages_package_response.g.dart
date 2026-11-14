// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_pos_banquets_packages_package_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerPosBanquetsPackagesPackageResponse
_$PatchV1OwnerPosBanquetsPackagesPackageResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1OwnerPosBanquetsPackagesPackageResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data143.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PatchV1OwnerPosBanquetsPackagesPackageResponseToJson(
  PatchV1OwnerPosBanquetsPackagesPackageResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
