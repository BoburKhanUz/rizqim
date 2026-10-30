// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data222.dart';

part 'post_v1_pos_orders_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersResponse {
  const PostV1PosOrdersResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PosOrdersResponseFromJson(json);

  final bool success;
  final String message;
  final Data222 data;

  Map<String, Object?> toJson() => _$PostV1PosOrdersResponseToJson(this);
}
