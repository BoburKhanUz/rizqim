// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data189.dart';

part 'post_v1_staff_orders_uuid_status_response.g.dart';

@JsonSerializable()
class PostV1StaffOrdersUuidStatusResponse {
  const PostV1StaffOrdersUuidStatusResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1StaffOrdersUuidStatusResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1StaffOrdersUuidStatusResponseFromJson(json);

  final bool success;
  final String message;
  final Data189 data;

  Map<String, Object?> toJson() =>
      _$PostV1StaffOrdersUuidStatusResponseToJson(this);
}
