// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_settings_upload_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerSettingsUploadResponse _$PostV1OwnerSettingsUploadResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerSettingsUploadResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data72.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerSettingsUploadResponseToJson(
  PostV1OwnerSettingsUploadResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
