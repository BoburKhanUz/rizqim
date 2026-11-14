// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_banquets_uuid_start_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBanquetsUuidStartResponse _$PostV1PosBanquetsUuidStartResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBanquetsUuidStartResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data242.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBanquetsUuidStartResponseToJson(
  PostV1PosBanquetsUuidStartResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
