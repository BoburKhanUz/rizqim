// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_merge_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidMergeRequestBody {
  const V1PosOrdersUuidMergeRequestBody({
    required this.version,
    required this.sourceUuid,
    required this.sourceVersion,
  });

  factory V1PosOrdersUuidMergeRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosOrdersUuidMergeRequestBodyFromJson(json);

  final int version;
  @JsonKey(name: 'source_uuid')
  final String sourceUuid;
  @JsonKey(name: 'source_version')
  final int sourceVersion;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidMergeRequestBodyToJson(this);
}
