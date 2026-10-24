// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data161.dart';

part 'post_v1_pos_orders_uuid_guests_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidGuestsResponse {
  const PostV1PosOrdersUuidGuestsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidGuestsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidGuestsResponseFromJson(json);

  final bool success;
  final String message;
  final Data161 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidGuestsResponseToJson(this);
}
