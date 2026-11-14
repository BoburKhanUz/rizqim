// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_banquets_uuid_settle_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBanquetsUuidSettleResponse
_$PostV1PosBanquetsUuidSettleResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosBanquetsUuidSettleResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data237.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosBanquetsUuidSettleResponseToJson(
  PostV1PosBanquetsUuidSettleResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
