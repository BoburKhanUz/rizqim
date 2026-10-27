// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data31.dart';

part 'post_v1_devices_response.g.dart';

@JsonSerializable()
class PostV1DevicesResponse {
  const PostV1DevicesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1DevicesResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1DevicesResponseFromJson(json);

  final bool success;
  final String message;
  final Data31 data;

  Map<String, Object?> toJson() => _$PostV1DevicesResponseToJson(this);
}
