// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_refunds_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosRefundsResponse _$PostV1PosRefundsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosRefundsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data162.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosRefundsResponseToJson(
  PostV1PosRefundsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
