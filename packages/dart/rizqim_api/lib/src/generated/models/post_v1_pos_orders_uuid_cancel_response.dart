// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data177.dart';

part 'post_v1_pos_orders_uuid_cancel_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidCancelResponse {
  const PostV1PosOrdersUuidCancelResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidCancelResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidCancelResponseFromJson(json);

  final bool success;
  final String message;
  final Data177 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidCancelResponseToJson(this);
}
