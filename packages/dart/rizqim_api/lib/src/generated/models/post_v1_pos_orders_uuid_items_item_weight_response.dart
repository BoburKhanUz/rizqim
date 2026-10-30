// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data239.dart';

part 'post_v1_pos_orders_uuid_items_item_weight_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidItemsItemWeightResponse {
  const PostV1PosOrdersUuidItemsItemWeightResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidItemsItemWeightResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidItemsItemWeightResponseFromJson(json);

  final bool success;
  final String message;
  final Data239 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidItemsItemWeightResponseToJson(this);
}
