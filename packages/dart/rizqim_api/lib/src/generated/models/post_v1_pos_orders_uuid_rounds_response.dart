// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data157.dart';

part 'post_v1_pos_orders_uuid_rounds_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidRoundsResponse {
  const PostV1PosOrdersUuidRoundsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidRoundsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidRoundsResponseFromJson(json);

  final bool success;
  final String message;
  final Data157 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidRoundsResponseToJson(this);
}
