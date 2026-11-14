// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'ingredients3.dart';

part 'osh3.g.dart';

@JsonSerializable()
class Osh3 {
  const Osh3({
    this.personsPerKg,
    this.ingredients,
    this.kazans,
    this.productIds,
  });

  factory Osh3.fromJson(Map<String, Object?> json) => _$Osh3FromJson(json);

  @JsonKey(name: 'persons_per_kg')
  final num? personsPerKg;
  final List<Ingredients3>? ingredients;
  final List<int>? kazans;
  @JsonKey(name: 'product_ids')
  final List<int>? productIds;

  Map<String, Object?> toJson() => _$Osh3ToJson(this);
}
