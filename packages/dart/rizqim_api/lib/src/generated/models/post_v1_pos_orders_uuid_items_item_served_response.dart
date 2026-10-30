// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data237.dart';

part 'post_v1_pos_orders_uuid_items_item_served_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidItemsItemServedResponse {
  const PostV1PosOrdersUuidItemsItemServedResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidItemsItemServedResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidItemsItemServedResponseFromJson(json);

  final bool success;
  final String message;
  final Data237 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidItemsItemServedResponseToJson(this);
}
