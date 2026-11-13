// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_vouchers_sell_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosVouchersSellResponse _$PostV1PosVouchersSellResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosVouchersSellResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data299.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosVouchersSellResponseToJson(
  PostV1PosVouchersSellResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
