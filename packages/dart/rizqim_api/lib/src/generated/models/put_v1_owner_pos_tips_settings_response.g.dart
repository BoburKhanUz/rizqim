// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_pos_tips_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerPosTipsSettingsResponse _$PutV1OwnerPosTipsSettingsResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerPosTipsSettingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data136.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerPosTipsSettingsResponseToJson(
  PutV1OwnerPosTipsSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
