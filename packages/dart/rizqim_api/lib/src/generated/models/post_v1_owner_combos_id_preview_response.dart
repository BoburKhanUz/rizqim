// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data62.dart';

part 'post_v1_owner_combos_id_preview_response.g.dart';

@JsonSerializable()
class PostV1OwnerCombosIdPreviewResponse {
  const PostV1OwnerCombosIdPreviewResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerCombosIdPreviewResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerCombosIdPreviewResponseFromJson(json);

  final bool success;
  final String message;
  final Data62 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerCombosIdPreviewResponseToJson(this);
}
