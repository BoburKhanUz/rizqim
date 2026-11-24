// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_fiscal_uzqr_tills_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerFiscalUzqrTillsUuidResponse
_$PutV1OwnerFiscalUzqrTillsUuidResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerFiscalUzqrTillsUuidResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data104.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1OwnerFiscalUzqrTillsUuidResponseToJson(
  PutV1OwnerFiscalUzqrTillsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
