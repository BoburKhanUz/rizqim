// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bills_uuid_print_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBillsUuidPrintResponse _$PostV1PosBillsUuidPrintResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBillsUuidPrintResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data121.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBillsUuidPrintResponseToJson(
  PostV1PosBillsUuidPrintResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
