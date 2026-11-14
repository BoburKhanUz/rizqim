// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_pos_banquets_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1PosBanquetsUuidResponse _$PatchV1PosBanquetsUuidResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1PosBanquetsUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data225.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PatchV1PosBanquetsUuidResponseToJson(
  PatchV1PosBanquetsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
