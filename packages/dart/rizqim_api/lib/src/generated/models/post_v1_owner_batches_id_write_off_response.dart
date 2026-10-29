// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data38.dart';

part 'post_v1_owner_batches_id_write_off_response.g.dart';

@JsonSerializable()
class PostV1OwnerBatchesIdWriteOffResponse {
  const PostV1OwnerBatchesIdWriteOffResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerBatchesIdWriteOffResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerBatchesIdWriteOffResponseFromJson(json);

  final bool success;
  final String message;
  final Data38 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerBatchesIdWriteOffResponseToJson(this);
}
