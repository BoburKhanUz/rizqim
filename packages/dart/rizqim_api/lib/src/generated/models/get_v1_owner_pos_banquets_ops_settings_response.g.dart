// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_banquets_ops_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosBanquetsOpsSettingsResponse
_$GetV1OwnerPosBanquetsOpsSettingsResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerPosBanquetsOpsSettingsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data138.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerPosBanquetsOpsSettingsResponseToJson(
  GetV1OwnerPosBanquetsOpsSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
