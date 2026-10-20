// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order.dart';

part 'post_v1_orders_uuid_complete_response.g.dart';

@JsonSerializable()
class PostV1OrdersUuidCompleteResponse {
  const PostV1OrdersUuidCompleteResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OrdersUuidCompleteResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OrdersUuidCompleteResponseFromJson(json);

  final bool success;
  final String message;
  final Order data;

  Map<String, Object?> toJson() =>
      _$PostV1OrdersUuidCompleteResponseToJson(this);
}
