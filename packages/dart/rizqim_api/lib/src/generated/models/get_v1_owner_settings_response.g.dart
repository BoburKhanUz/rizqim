// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerSettingsResponse _$GetV1OwnerSettingsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerSettingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data49.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerSettingsResponseToJson(
  GetV1OwnerSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
