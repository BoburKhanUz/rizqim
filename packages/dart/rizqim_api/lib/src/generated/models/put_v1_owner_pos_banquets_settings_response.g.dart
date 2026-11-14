// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_pos_banquets_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerPosBanquetsSettingsResponse
_$PutV1OwnerPosBanquetsSettingsResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerPosBanquetsSettingsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data148.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1OwnerPosBanquetsSettingsResponseToJson(
  PutV1OwnerPosBanquetsSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
