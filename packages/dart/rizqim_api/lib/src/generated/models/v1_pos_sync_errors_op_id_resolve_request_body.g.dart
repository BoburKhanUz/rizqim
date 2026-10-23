// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_sync_errors_op_id_resolve_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosSyncErrorsOpIdResolveRequestBody
_$V1PosSyncErrorsOpIdResolveRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosSyncErrorsOpIdResolveRequestBody(
      resolution: Resolution.fromJson(json['resolution'] as String),
      note: json['note'] as String?,
    );

Map<String, dynamic> _$V1PosSyncErrorsOpIdResolveRequestBodyToJson(
  V1PosSyncErrorsOpIdResolveRequestBody instance,
) => <String, dynamic>{
  'resolution': instance.resolution,
  'note': instance.note,
};
