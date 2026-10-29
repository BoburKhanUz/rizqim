// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_permissions_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerPermissionsResponse _$PutV1OwnerPermissionsResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerPermissionsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data107.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerPermissionsResponseToJson(
  PutV1OwnerPermissionsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
