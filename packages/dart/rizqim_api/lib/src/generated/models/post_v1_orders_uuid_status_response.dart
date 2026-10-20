// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order.dart';

part 'post_v1_orders_uuid_status_response.g.dart';

@JsonSerializable()
class PostV1OrdersUuidStatusResponse {
  const PostV1OrdersUuidStatusResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OrdersUuidStatusResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OrdersUuidStatusResponseFromJson(json);

  final bool success;
  final String message;
  final Order data;

  Map<String, Object?> toJson() => _$PostV1OrdersUuidStatusResponseToJson(this);
}
