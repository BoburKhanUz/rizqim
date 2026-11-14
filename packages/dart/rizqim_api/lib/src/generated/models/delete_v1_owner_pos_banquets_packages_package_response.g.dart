// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_pos_banquets_packages_package_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerPosBanquetsPackagesPackageResponse
_$DeleteV1OwnerPosBanquetsPackagesPackageResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerPosBanquetsPackagesPackageResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data142.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$DeleteV1OwnerPosBanquetsPackagesPackageResponseToJson(
  DeleteV1OwnerPosBanquetsPackagesPackageResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
