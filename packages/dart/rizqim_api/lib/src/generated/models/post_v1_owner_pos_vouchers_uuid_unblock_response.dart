// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data148.dart';

part 'post_v1_owner_pos_vouchers_uuid_unblock_response.g.dart';

@JsonSerializable()
class PostV1OwnerPosVouchersUuidUnblockResponse {
  const PostV1OwnerPosVouchersUuidUnblockResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerPosVouchersUuidUnblockResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerPosVouchersUuidUnblockResponseFromJson(json);

  final bool success;
  final String message;
  final Data148 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerPosVouchersUuidUnblockResponseToJson(this);
}
