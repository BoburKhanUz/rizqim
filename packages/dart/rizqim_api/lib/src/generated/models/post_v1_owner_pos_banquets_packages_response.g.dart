// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_pos_banquets_packages_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerPosBanquetsPackagesResponse
_$PostV1OwnerPosBanquetsPackagesResponseFromJson(Map<String, dynamic> json) =>
    PostV1OwnerPosBanquetsPackagesResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data141.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1OwnerPosBanquetsPackagesResponseToJson(
  PostV1OwnerPosBanquetsPackagesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
