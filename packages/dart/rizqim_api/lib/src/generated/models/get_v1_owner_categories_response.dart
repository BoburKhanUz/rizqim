// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'category.dart';

part 'get_v1_owner_categories_response.g.dart';

@JsonSerializable()
class GetV1OwnerCategoriesResponse {
  const GetV1OwnerCategoriesResponse({
    required this.success,
    required this.data,
  });

  factory GetV1OwnerCategoriesResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerCategoriesResponseFromJson(json);

  final bool success;
  final List<Category> data;

  Map<String, Object?> toJson() => _$GetV1OwnerCategoriesResponseToJson(this);
}
