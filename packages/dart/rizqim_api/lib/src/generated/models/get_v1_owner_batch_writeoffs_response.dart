// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data32.dart';

part 'get_v1_owner_batch_writeoffs_response.g.dart';

@JsonSerializable()
class GetV1OwnerBatchWriteoffsResponse {
  const GetV1OwnerBatchWriteoffsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerBatchWriteoffsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerBatchWriteoffsResponseFromJson(json);

  final bool success;
  final String message;
  final Data32 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerBatchWriteoffsResponseToJson(this);
}
