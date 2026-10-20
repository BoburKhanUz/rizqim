// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data3.dart';

part 'post_v1_auth_refresh_response.g.dart';

@JsonSerializable()
class PostV1AuthRefreshResponse {
  const PostV1AuthRefreshResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AuthRefreshResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1AuthRefreshResponseFromJson(json);

  final bool success;
  final String message;
  final Data3 data;

  Map<String, Object?> toJson() => _$PostV1AuthRefreshResponseToJson(this);
}
