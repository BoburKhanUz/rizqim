// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_fiscal_receipts_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerFiscalReceiptsResponse _$GetV1OwnerFiscalReceiptsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerFiscalReceiptsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data80.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerFiscalReceiptsResponseToJson(
  GetV1OwnerFiscalReceiptsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
