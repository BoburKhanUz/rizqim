// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data164.dart';

part 'post_v1_owner_pos_sync_import_response.g.dart';

@JsonSerializable()
class PostV1OwnerPosSyncImportResponse {
  const PostV1OwnerPosSyncImportResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerPosSyncImportResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerPosSyncImportResponseFromJson(json);

  final bool success;
  final String message;
  final Data164 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerPosSyncImportResponseToJson(this);
}
