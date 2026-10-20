// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerSettingsResponse _$PostV1OwnerSettingsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerSettingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'] == null
      ? null
      : Store.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerSettingsResponseToJson(
  PostV1OwnerSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
