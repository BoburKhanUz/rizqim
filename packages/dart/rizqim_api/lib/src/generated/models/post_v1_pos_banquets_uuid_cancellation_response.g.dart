// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_banquets_uuid_cancellation_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosBanquetsUuidCancellationResponse
_$PostV1PosBanquetsUuidCancellationResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosBanquetsUuidCancellationResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data227.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosBanquetsUuidCancellationResponseToJson(
  PostV1PosBanquetsUuidCancellationResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
