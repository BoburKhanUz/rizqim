// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data224.dart';

part 'post_v1_pos_orders_uuid_accept_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidAcceptResponse {
  const PostV1PosOrdersUuidAcceptResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidAcceptResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidAcceptResponseFromJson(json);

  final bool success;
  final String message;
  final Data224 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidAcceptResponseToJson(this);
}
