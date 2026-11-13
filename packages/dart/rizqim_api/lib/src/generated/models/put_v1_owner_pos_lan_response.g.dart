// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_pos_lan_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerPosLanResponse _$PutV1OwnerPosLanResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerPosLanResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data130.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerPosLanResponseToJson(
  PutV1OwnerPosLanResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
