// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data30.dart';

part 'post_v1_owner_batches_id_close_response.g.dart';

@JsonSerializable()
class PostV1OwnerBatchesIdCloseResponse {
  const PostV1OwnerBatchesIdCloseResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerBatchesIdCloseResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerBatchesIdCloseResponseFromJson(json);

  final bool success;
  final String message;
  final Data30 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerBatchesIdCloseResponseToJson(this);
}
