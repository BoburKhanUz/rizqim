// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data184.dart';

part 'post_v1_staff_orders_uuid_items_item_recall_response.g.dart';

@JsonSerializable()
class PostV1StaffOrdersUuidItemsItemRecallResponse {
  const PostV1StaffOrdersUuidItemsItemRecallResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1StaffOrdersUuidItemsItemRecallResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1StaffOrdersUuidItemsItemRecallResponseFromJson(json);

  final bool success;
  final String message;
  final Data184 data;

  Map<String, Object?> toJson() =>
      _$PostV1StaffOrdersUuidItemsItemRecallResponseToJson(this);
}
