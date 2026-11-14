// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_banquets_packages_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosBanquetsPackagesResponse
_$GetV1OwnerPosBanquetsPackagesResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerPosBanquetsPackagesResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data140.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerPosBanquetsPackagesResponseToJson(
  GetV1OwnerPosBanquetsPackagesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
