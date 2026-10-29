// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data179.dart';

part 'post_v1_pos_orders_uuid_call_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidCallResponse {
  const PostV1PosOrdersUuidCallResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidCallResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PosOrdersUuidCallResponseFromJson(json);

  final bool success;
  final String message;
  final Data179 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidCallResponseToJson(this);
}
