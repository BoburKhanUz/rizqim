// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data25.dart';

part 'post_v1_owner_batches_response.g.dart';

@JsonSerializable()
class PostV1OwnerBatchesResponse {
  const PostV1OwnerBatchesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerBatchesResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerBatchesResponseFromJson(json);

  final bool success;
  final String message;
  final Data25 data;

  Map<String, Object?> toJson() => _$PostV1OwnerBatchesResponseToJson(this);
}
