// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_fiscal_settings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerFiscalSettingsResponse _$GetV1OwnerFiscalSettingsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerFiscalSettingsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data96.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerFiscalSettingsResponseToJson(
  GetV1OwnerFiscalSettingsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
