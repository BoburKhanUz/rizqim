// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_permissions_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPermissionsResponse _$GetV1OwnerPermissionsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPermissionsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data41.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPermissionsResponseToJson(
  GetV1OwnerPermissionsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
