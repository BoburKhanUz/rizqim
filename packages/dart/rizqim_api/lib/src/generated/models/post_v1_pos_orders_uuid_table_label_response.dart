// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data250.dart';

part 'post_v1_pos_orders_uuid_table_label_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidTableLabelResponse {
  const PostV1PosOrdersUuidTableLabelResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidTableLabelResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidTableLabelResponseFromJson(json);

  final bool success;
  final String message;
  final Data250 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidTableLabelResponseToJson(this);
}
