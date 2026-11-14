// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_banquets_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosBanquetsSettingsResponse
_$GetV1OwnerPosBanquetsSettingsResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerPosBanquetsSettingsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data147.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerPosBanquetsSettingsResponseToJson(
  GetV1OwnerPosBanquetsSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
