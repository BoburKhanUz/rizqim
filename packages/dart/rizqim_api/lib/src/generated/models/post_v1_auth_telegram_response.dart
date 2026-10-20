// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data4.dart';

part 'post_v1_auth_telegram_response.g.dart';

@JsonSerializable()
class PostV1AuthTelegramResponse {
  const PostV1AuthTelegramResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AuthTelegramResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1AuthTelegramResponseFromJson(json);

  final bool success;
  final String message;
  final Data4 data;

  Map<String, Object?> toJson() => _$PostV1AuthTelegramResponseToJson(this);
}
