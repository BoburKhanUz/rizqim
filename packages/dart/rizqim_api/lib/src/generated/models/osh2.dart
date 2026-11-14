// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'ingredients2.dart';

part 'osh2.g.dart';

@JsonSerializable()
class Osh2 {
  const Osh2({
    required this.personsPerKg,
    required this.productIds,
    required this.ingredients,
    required this.kazans,
  });

  factory Osh2.fromJson(Map<String, Object?> json) => _$Osh2FromJson(json);

  @JsonKey(name: 'persons_per_kg')
  final String personsPerKg;
  @JsonKey(name: 'product_ids')
  final List<int> productIds;
  final List<Ingredients2> ingredients;
  final List<int> kazans;

  Map<String, Object?> toJson() => _$Osh2ToJson(this);
}
