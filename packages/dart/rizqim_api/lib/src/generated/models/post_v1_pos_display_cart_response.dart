// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data216.dart';

part 'post_v1_pos_display_cart_response.g.dart';

@JsonSerializable()
class PostV1PosDisplayCartResponse {
  const PostV1PosDisplayCartResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosDisplayCartResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PosDisplayCartResponseFromJson(json);

  final bool success;
  final String message;
  final Data216 data;

  Map<String, Object?> toJson() => _$PostV1PosDisplayCartResponseToJson(this);
}
