// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_me_permissions_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1MePermissionsResponse _$GetV1MePermissionsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1MePermissionsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data21.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1MePermissionsResponseToJson(
  GetV1MePermissionsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
