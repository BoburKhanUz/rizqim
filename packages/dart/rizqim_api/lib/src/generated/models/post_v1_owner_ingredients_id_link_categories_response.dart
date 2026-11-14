// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'post_v1_owner_ingredients_id_link_categories_response.g.dart';

@JsonSerializable()
class PostV1OwnerIngredientsIdLinkCategoriesResponse {
  const PostV1OwnerIngredientsIdLinkCategoriesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerIngredientsIdLinkCategoriesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerIngredientsIdLinkCategoriesResponseFromJson(json);

  final bool success;
  final String message;
  final List<dynamic> data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerIngredientsIdLinkCategoriesResponseToJson(this);
}
