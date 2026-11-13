// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_bills_uuid_qr_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBillsUuidQrResponse _$PostV1PosBillsUuidQrResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBillsUuidQrResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data201.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBillsUuidQrResponseToJson(
  PostV1PosBillsUuidQrResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
