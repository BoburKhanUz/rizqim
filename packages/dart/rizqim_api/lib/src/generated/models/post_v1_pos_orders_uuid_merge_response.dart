// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data164.dart';

part 'post_v1_pos_orders_uuid_merge_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidMergeResponse {
  const PostV1PosOrdersUuidMergeResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidMergeResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidMergeResponseFromJson(json);

  final bool success;
  final String message;
  final Data164 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidMergeResponseToJson(this);
}
