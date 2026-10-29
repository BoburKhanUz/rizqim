// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_fiscal_readiness_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerFiscalReadinessResponse _$GetV1OwnerFiscalReadinessResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerFiscalReadinessResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data76.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerFiscalReadinessResponseToJson(
  GetV1OwnerFiscalReadinessResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
