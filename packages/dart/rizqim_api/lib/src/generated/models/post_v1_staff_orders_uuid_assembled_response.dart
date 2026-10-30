// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data281.dart';

part 'post_v1_staff_orders_uuid_assembled_response.g.dart';

@JsonSerializable()
class PostV1StaffOrdersUuidAssembledResponse {
  const PostV1StaffOrdersUuidAssembledResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1StaffOrdersUuidAssembledResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1StaffOrdersUuidAssembledResponseFromJson(json);

  final bool success;
  final String message;
  final Data281 data;

  Map<String, Object?> toJson() =>
      _$PostV1StaffOrdersUuidAssembledResponseToJson(this);
}
