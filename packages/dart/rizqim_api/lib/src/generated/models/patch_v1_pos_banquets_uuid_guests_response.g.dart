// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_pos_banquets_uuid_guests_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1PosBanquetsUuidGuestsResponse
_$PatchV1PosBanquetsUuidGuestsResponseFromJson(Map<String, dynamic> json) =>
    PatchV1PosBanquetsUuidGuestsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data231.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PatchV1PosBanquetsUuidGuestsResponseToJson(
  PatchV1PosBanquetsUuidGuestsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
