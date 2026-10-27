// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data136.dart';

part 'post_v1_owner_stop_list_response.g.dart';

@JsonSerializable()
class PostV1OwnerStopListResponse {
  const PostV1OwnerStopListResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerStopListResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerStopListResponseFromJson(json);

  final bool success;
  final String message;
  final Data136 data;

  Map<String, Object?> toJson() => _$PostV1OwnerStopListResponseToJson(this);
}
