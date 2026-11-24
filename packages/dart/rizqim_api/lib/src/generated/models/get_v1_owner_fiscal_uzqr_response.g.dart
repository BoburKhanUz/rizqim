// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_fiscal_uzqr_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerFiscalUzqrResponse _$GetV1OwnerFiscalUzqrResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerFiscalUzqrResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data103.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerFiscalUzqrResponseToJson(
  GetV1OwnerFiscalUzqrResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
