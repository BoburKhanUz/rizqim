// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'links.dart';

part 'v1_owner_ingredients_id_products_request_body.g.dart';

@JsonSerializable()
class V1OwnerIngredientsIdProductsRequestBody {
  const V1OwnerIngredientsIdProductsRequestBody({required this.links});

  factory V1OwnerIngredientsIdProductsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerIngredientsIdProductsRequestBodyFromJson(json);

  final List<Links> links;

  Map<String, Object?> toJson() =>
      _$V1OwnerIngredientsIdProductsRequestBodyToJson(this);
}
