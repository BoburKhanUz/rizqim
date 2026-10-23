// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data160.dart';

part 'post_v1_pos_orders_uuid_items_item_void_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidItemsItemVoidResponse {
  const PostV1PosOrdersUuidItemsItemVoidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidItemsItemVoidResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidItemsItemVoidResponseFromJson(json);

  final bool success;
  final String message;
  final Data160 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidItemsItemVoidResponseToJson(this);
}
