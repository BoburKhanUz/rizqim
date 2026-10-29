// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_staff_pay_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerStaffPaySettingsResponse _$PutV1OwnerStaffPaySettingsResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerStaffPaySettingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data147.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerStaffPaySettingsResponseToJson(
  PutV1OwnerStaffPaySettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
