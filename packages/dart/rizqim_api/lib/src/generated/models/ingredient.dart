// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'product.dart';
import 'store.dart';

part 'ingredient.g.dart';

@JsonSerializable()
class Ingredient {
  const Ingredient({
    required this.id,
    required this.uuid,
    required this.storeId,
    required this.name,
    required this.unit,
    required this.createdAt,
    required this.updatedAt,
    this.store,
    this.products,
  });

  factory Ingredient.fromJson(Map<String, Object?> json) =>
      _$IngredientFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'store_id')
  final int storeId;
  final String name;
  final String? unit;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Store? store;
  final List<Product>? products;

  Map<String, Object?> toJson() => _$IngredientToJson(this);
}
