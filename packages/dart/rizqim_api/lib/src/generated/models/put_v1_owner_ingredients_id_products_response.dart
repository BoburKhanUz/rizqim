// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'put_v1_owner_ingredients_id_products_response.g.dart';

@JsonSerializable()
class PutV1OwnerIngredientsIdProductsResponse {
  const PutV1OwnerIngredientsIdProductsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerIngredientsIdProductsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerIngredientsIdProductsResponseFromJson(json);

  final bool success;
  final String message;
  final String data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerIngredientsIdProductsResponseToJson(this);
}
