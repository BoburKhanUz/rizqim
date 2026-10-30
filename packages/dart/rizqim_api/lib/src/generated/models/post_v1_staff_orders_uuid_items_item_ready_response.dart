// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data282.dart';

part 'post_v1_staff_orders_uuid_items_item_ready_response.g.dart';

@JsonSerializable()
class PostV1StaffOrdersUuidItemsItemReadyResponse {
  const PostV1StaffOrdersUuidItemsItemReadyResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1StaffOrdersUuidItemsItemReadyResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1StaffOrdersUuidItemsItemReadyResponseFromJson(json);

  final bool success;
  final String message;
  final Data282 data;

  Map<String, Object?> toJson() =>
      _$PostV1StaffOrdersUuidItemsItemReadyResponseToJson(this);
}
