// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_ingredients_id_link_categories_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerIngredientsIdLinkCategoriesRequestBody
_$V1OwnerIngredientsIdLinkCategoriesRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerIngredientsIdLinkCategoriesRequestBody(
  categoryIds: (json['category_ids'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  isCritical: json['is_critical'] as bool?,
);

Map<String, dynamic> _$V1OwnerIngredientsIdLinkCategoriesRequestBodyToJson(
  V1OwnerIngredientsIdLinkCategoriesRequestBody instance,
) => <String, dynamic>{
  'category_ids': instance.categoryIds,
  'is_critical': instance.isCritical,
};
