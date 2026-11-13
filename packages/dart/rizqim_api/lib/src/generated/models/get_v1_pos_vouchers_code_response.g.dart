// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_vouchers_code_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosVouchersCodeResponse _$GetV1PosVouchersCodeResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosVouchersCodeResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data300.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosVouchersCodeResponseToJson(
  GetV1PosVouchersCodeResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
