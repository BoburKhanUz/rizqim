// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data112.dart';

part 'post_v1_owner_pos_format_template_response.g.dart';

@JsonSerializable()
class PostV1OwnerPosFormatTemplateResponse {
  const PostV1OwnerPosFormatTemplateResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerPosFormatTemplateResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerPosFormatTemplateResponseFromJson(json);

  final bool success;
  final String message;
  final Data112 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerPosFormatTemplateResponseToJson(this);
}
