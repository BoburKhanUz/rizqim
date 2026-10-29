// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_fiscal_bank_reports_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerFiscalBankReportsUuidResponse
_$PutV1OwnerFiscalBankReportsUuidResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerFiscalBankReportsUuidResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data74.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1OwnerFiscalBankReportsUuidResponseToJson(
  PutV1OwnerFiscalBankReportsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
