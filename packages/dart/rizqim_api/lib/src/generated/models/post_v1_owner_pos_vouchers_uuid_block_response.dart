// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data147.dart';

part 'post_v1_owner_pos_vouchers_uuid_block_response.g.dart';

@JsonSerializable()
class PostV1OwnerPosVouchersUuidBlockResponse {
  const PostV1OwnerPosVouchersUuidBlockResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerPosVouchersUuidBlockResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerPosVouchersUuidBlockResponseFromJson(json);

  final bool success;
  final String message;
  final Data147 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerPosVouchersUuidBlockResponseToJson(this);
}
