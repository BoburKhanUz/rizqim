// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data186.dart';

part 'post_v1_pos_orders_uuid_move_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidMoveResponse {
  const PostV1PosOrdersUuidMoveResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidMoveResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PosOrdersUuidMoveResponseFromJson(json);

  final bool success;
  final String message;
  final Data186 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidMoveResponseToJson(this);
}
