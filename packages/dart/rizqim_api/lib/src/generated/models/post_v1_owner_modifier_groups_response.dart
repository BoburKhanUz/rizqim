// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data82.dart';

part 'post_v1_owner_modifier_groups_response.g.dart';

@JsonSerializable()
class PostV1OwnerModifierGroupsResponse {
  const PostV1OwnerModifierGroupsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerModifierGroupsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerModifierGroupsResponseFromJson(json);

  final bool success;
  final String message;
  final Data82 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerModifierGroupsResponseToJson(this);
}
