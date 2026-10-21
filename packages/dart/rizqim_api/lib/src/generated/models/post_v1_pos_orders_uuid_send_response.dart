// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data158.dart';

part 'post_v1_pos_orders_uuid_send_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidSendResponse {
  const PostV1PosOrdersUuidSendResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidSendResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PosOrdersUuidSendResponseFromJson(json);

  final bool success;
  final String message;
  final Data158 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidSendResponseToJson(this);
}
