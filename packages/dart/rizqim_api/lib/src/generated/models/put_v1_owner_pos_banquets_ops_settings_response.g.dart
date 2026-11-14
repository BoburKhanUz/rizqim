// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_pos_banquets_ops_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerPosBanquetsOpsSettingsResponse
_$PutV1OwnerPosBanquetsOpsSettingsResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerPosBanquetsOpsSettingsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data139.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1OwnerPosBanquetsOpsSettingsResponseToJson(
  PutV1OwnerPosBanquetsOpsSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
