// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_banquets_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBanquetsUuidResponse _$GetV1PosBanquetsUuidResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosBanquetsUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data224.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosBanquetsUuidResponseToJson(
  GetV1PosBanquetsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
