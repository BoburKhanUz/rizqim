// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data227.dart';

part 'post_v1_pos_orders_uuid_reject_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidRejectResponse {
  const PostV1PosOrdersUuidRejectResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidRejectResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidRejectResponseFromJson(json);

  final bool success;
  final String message;
  final Data227 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidRejectResponseToJson(this);
}
