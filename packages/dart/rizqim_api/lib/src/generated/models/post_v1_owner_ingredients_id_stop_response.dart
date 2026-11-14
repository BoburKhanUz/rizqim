// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data107.dart';

part 'post_v1_owner_ingredients_id_stop_response.g.dart';

@JsonSerializable()
class PostV1OwnerIngredientsIdStopResponse {
  const PostV1OwnerIngredientsIdStopResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerIngredientsIdStopResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerIngredientsIdStopResponseFromJson(json);

  final bool success;
  final String message;
  final Data107 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerIngredientsIdStopResponseToJson(this);
}
