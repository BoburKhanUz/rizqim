// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data254.dart';

part 'post_v1_pos_queue_blocks_response.g.dart';

@JsonSerializable()
class PostV1PosQueueBlocksResponse {
  const PostV1PosQueueBlocksResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosQueueBlocksResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PosQueueBlocksResponseFromJson(json);

  final bool success;
  final String message;
  final Data254 data;

  Map<String, Object?> toJson() => _$PostV1PosQueueBlocksResponseToJson(this);
}
