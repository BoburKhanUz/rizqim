// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data226.dart';

part 'post_v1_pos_orders_uuid_bills_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidBillsResponse {
  const PostV1PosOrdersUuidBillsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidBillsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidBillsResponseFromJson(json);

  final bool success;
  final String message;
  final Data226 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidBillsResponseToJson(this);
}
