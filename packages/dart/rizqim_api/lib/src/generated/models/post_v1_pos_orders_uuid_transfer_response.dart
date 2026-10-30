// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data251.dart';

part 'post_v1_pos_orders_uuid_transfer_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidTransferResponse {
  const PostV1PosOrdersUuidTransferResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidTransferResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidTransferResponseFromJson(json);

  final bool success;
  final String message;
  final Data251 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidTransferResponseToJson(this);
}
