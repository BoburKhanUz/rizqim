// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'ingredients6.dart';
import 'kazans2.dart';

part 'osh5.g.dart';

@JsonSerializable()
class Osh5 {
  const Osh5({
    required this.persons,
    required this.personsPerKg,
    required this.oshKg,
    required this.ingredients,
    required this.kazans,
    required this.kazanCapacityKg,
  });

  factory Osh5.fromJson(Map<String, Object?> json) => _$Osh5FromJson(json);

  final int persons;
  @JsonKey(name: 'persons_per_kg')
  final String personsPerKg;
  @JsonKey(name: 'osh_kg')
  final String oshKg;
  final List<Ingredients6> ingredients;
  final List<Kazans2> kazans;
  @JsonKey(name: 'kazan_capacity_kg')
  final String kazanCapacityKg;

  Map<String, Object?> toJson() => _$Osh5ToJson(this);
}
