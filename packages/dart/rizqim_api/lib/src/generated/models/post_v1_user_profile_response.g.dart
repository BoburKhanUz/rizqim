// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_user_profile_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1UserProfileResponse _$PostV1UserProfileResponseFromJson(
  Map<String, dynamic> json,
) => PostV1UserProfileResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: User.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1UserProfileResponseToJson(
  PostV1UserProfileResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
