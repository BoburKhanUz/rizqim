// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_staff_pay_work_sessions_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerStaffPayWorkSessionsResponse
_$PostV1OwnerStaffPayWorkSessionsResponseFromJson(Map<String, dynamic> json) =>
    PostV1OwnerStaffPayWorkSessionsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data173.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1OwnerStaffPayWorkSessionsResponseToJson(
  PostV1OwnerStaffPayWorkSessionsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
