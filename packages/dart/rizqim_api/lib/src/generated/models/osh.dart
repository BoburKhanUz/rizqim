// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'ingredients.dart';

part 'osh.g.dart';

@JsonSerializable()
class Osh {
  const Osh({
    required this.personsPerKg,
    required this.productIds,
    required this.ingredients,
    required this.kazans,
  });

  factory Osh.fromJson(Map<String, Object?> json) => _$OshFromJson(json);

  @JsonKey(name: 'persons_per_kg')
  final String personsPerKg;
  @JsonKey(name: 'product_ids')
  final List<int> productIds;
  final List<Ingredients> ingredients;
  final List<int> kazans;

  Map<String, Object?> toJson() => _$OshToJson(this);
}
