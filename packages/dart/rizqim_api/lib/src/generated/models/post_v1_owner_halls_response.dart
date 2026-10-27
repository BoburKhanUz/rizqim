// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data71.dart';

part 'post_v1_owner_halls_response.g.dart';

@JsonSerializable()
class PostV1OwnerHallsResponse {
  const PostV1OwnerHallsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerHallsResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerHallsResponseFromJson(json);

  final bool success;
  final String message;
  final Data71 data;

  Map<String, Object?> toJson() => _$PostV1OwnerHallsResponseToJson(this);
}
