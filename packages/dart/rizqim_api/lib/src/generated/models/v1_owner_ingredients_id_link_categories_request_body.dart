// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_ingredients_id_link_categories_request_body.g.dart';

@JsonSerializable()
class V1OwnerIngredientsIdLinkCategoriesRequestBody {
  const V1OwnerIngredientsIdLinkCategoriesRequestBody({
    required this.categoryIds,
    this.isCritical,
  });

  factory V1OwnerIngredientsIdLinkCategoriesRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerIngredientsIdLinkCategoriesRequestBodyFromJson(json);

  @JsonKey(name: 'category_ids')
  final List<int> categoryIds;
  @JsonKey(name: 'is_critical')
  final bool? isCritical;

  Map<String, Object?> toJson() =>
      _$V1OwnerIngredientsIdLinkCategoriesRequestBodyToJson(this);
}
