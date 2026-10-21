// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_merge_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidMergeRequestBody _$V1PosOrdersUuidMergeRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidMergeRequestBody(
  version: (json['version'] as num).toInt(),
  sourceUuid: json['source_uuid'] as String,
  sourceVersion: (json['source_version'] as num).toInt(),
);

Map<String, dynamic> _$V1PosOrdersUuidMergeRequestBodyToJson(
  V1PosOrdersUuidMergeRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'source_uuid': instance.sourceUuid,
  'source_version': instance.sourceVersion,
};
