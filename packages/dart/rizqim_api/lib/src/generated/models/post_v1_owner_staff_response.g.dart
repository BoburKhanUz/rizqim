// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_staff_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerStaffResponse _$PostV1OwnerStaffResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerStaffResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data131.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerStaffResponseToJson(
  PostV1OwnerStaffResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
