// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data113.dart';

part 'post_v1_owner_meal_sets_response.g.dart';

@JsonSerializable()
class PostV1OwnerMealSetsResponse {
  const PostV1OwnerMealSetsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerMealSetsResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerMealSetsResponseFromJson(json);

  final bool success;
  final String message;
  final Data113 data;

  Map<String, Object?> toJson() => _$PostV1OwnerMealSetsResponseToJson(this);
}
