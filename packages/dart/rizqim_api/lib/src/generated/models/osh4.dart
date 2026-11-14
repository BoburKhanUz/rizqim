// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'ingredients5.dart';

part 'osh4.g.dart';

@JsonSerializable()
class Osh4 {
  const Osh4({
    required this.persons,
    required this.oshKg,
    required this.ingredients,
  });

  factory Osh4.fromJson(Map<String, Object?> json) => _$Osh4FromJson(json);

  final int persons;
  @JsonKey(name: 'osh_kg')
  final String oshKg;
  final List<Ingredients5> ingredients;

  Map<String, Object?> toJson() => _$Osh4ToJson(this);
}
