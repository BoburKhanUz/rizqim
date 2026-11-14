// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_banquets_uuid_freeze_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBanquetsUuidFreezeResponse
_$PostV1PosBanquetsUuidFreezeResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosBanquetsUuidFreezeResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data230.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosBanquetsUuidFreezeResponseToJson(
  PostV1PosBanquetsUuidFreezeResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
