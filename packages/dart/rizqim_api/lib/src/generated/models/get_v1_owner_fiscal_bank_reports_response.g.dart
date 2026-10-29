// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_fiscal_bank_reports_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerFiscalBankReportsResponse
_$GetV1OwnerFiscalBankReportsResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerFiscalBankReportsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: (json['data'] as List<dynamic>)
          .map((e) => Data75.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$GetV1OwnerFiscalBankReportsResponseToJson(
  GetV1OwnerFiscalBankReportsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
