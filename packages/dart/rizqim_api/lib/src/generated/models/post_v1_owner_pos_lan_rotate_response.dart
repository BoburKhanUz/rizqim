// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data154.dart';

part 'post_v1_owner_pos_lan_rotate_response.g.dart';

@JsonSerializable()
class PostV1OwnerPosLanRotateResponse {
  const PostV1OwnerPosLanRotateResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerPosLanRotateResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerPosLanRotateResponseFromJson(json);

  final bool success;
  final String message;
  final Data154 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerPosLanRotateResponseToJson(this);
}
