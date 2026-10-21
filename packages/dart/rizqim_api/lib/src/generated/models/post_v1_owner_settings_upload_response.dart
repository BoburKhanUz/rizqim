// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data50.dart';

part 'post_v1_owner_settings_upload_response.g.dart';

@JsonSerializable()
class PostV1OwnerSettingsUploadResponse {
  const PostV1OwnerSettingsUploadResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerSettingsUploadResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerSettingsUploadResponseFromJson(json);

  final bool success;
  final String message;
  final Data50 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerSettingsUploadResponseToJson(this);
}
