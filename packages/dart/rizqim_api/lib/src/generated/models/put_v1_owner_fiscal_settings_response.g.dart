// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_fiscal_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerFiscalSettingsResponse _$PutV1OwnerFiscalSettingsResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerFiscalSettingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data90.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerFiscalSettingsResponseToJson(
  PutV1OwnerFiscalSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
