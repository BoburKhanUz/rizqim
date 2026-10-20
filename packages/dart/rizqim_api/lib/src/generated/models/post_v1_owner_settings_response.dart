// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'store.dart';

part 'post_v1_owner_settings_response.g.dart';

@JsonSerializable()
class PostV1OwnerSettingsResponse {
  const PostV1OwnerSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerSettingsResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Store? data;

  Map<String, Object?> toJson() => _$PostV1OwnerSettingsResponseToJson(this);
}
