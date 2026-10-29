// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bills_uuid_fiscal_retry_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBillsUuidFiscalRetryResponse
_$PostV1PosBillsUuidFiscalRetryResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosBillsUuidFiscalRetryResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data138.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosBillsUuidFiscalRetryResponseToJson(
  PostV1PosBillsUuidFiscalRetryResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
