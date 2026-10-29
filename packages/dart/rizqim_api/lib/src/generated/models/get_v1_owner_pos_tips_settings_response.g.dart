// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_tips_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosTipsSettingsResponse _$GetV1OwnerPosTipsSettingsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPosTipsSettingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data120.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPosTipsSettingsResponseToJson(
  GetV1OwnerPosTipsSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
