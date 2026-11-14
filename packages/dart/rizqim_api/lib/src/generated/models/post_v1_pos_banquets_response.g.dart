// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_banquets_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBanquetsResponse _$PostV1PosBanquetsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBanquetsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data219.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBanquetsResponseToJson(
  PostV1PosBanquetsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
