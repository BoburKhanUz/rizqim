// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_fiscal_receipts_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerFiscalReceiptsUuidResponse
_$GetV1OwnerFiscalReceiptsUuidResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerFiscalReceiptsUuidResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data79.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerFiscalReceiptsUuidResponseToJson(
  GetV1OwnerFiscalReceiptsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
