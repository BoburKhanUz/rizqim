// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'ingredients4.dart';
import 'kazans.dart';

part 'data229.g.dart';

@JsonSerializable()
class Data229 {
  const Data229({
    required this.persons,
    required this.personsPerKg,
    required this.oshKg,
    required this.ingredients,
    required this.kazans,
    required this.kazanCapacityKg,
  });

  factory Data229.fromJson(Map<String, Object?> json) =>
      _$Data229FromJson(json);

  final int persons;
  @JsonKey(name: 'persons_per_kg')
  final String personsPerKg;
  @JsonKey(name: 'osh_kg')
  final String oshKg;
  final List<Ingredients4> ingredients;
  final List<Kazans> kazans;
  @JsonKey(name: 'kazan_capacity_kg')
  final String kazanCapacityKg;

  Map<String, Object?> toJson() => _$Data229ToJson(this);
}
