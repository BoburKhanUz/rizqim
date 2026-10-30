// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_fiscal_receipts_uuid_retry_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerFiscalReceiptsUuidRetryResponse
_$PostV1OwnerFiscalReceiptsUuidRetryResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerFiscalReceiptsUuidRetryResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data82.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerFiscalReceiptsUuidRetryResponseToJson(
  PostV1OwnerFiscalReceiptsUuidRetryResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
