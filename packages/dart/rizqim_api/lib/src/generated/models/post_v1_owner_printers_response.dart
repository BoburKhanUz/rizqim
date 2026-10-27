// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data113.dart';

part 'post_v1_owner_printers_response.g.dart';

@JsonSerializable()
class PostV1OwnerPrintersResponse {
  const PostV1OwnerPrintersResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerPrintersResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerPrintersResponseFromJson(json);

  final bool success;
  final String message;
  final Data113 data;

  Map<String, Object?> toJson() => _$PostV1OwnerPrintersResponseToJson(this);
}
