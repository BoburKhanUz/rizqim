// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'stations4.dart';
import 'dishes3.dart';

part 'template3.g.dart';

@JsonSerializable()
class Template3 {
  const Template3({
    required this.profile,
    required this.stations,
    required this.dishes,
  });

  factory Template3.fromJson(Map<String, Object?> json) =>
      _$Template3FromJson(json);

  final String profile;
  final List<Stations4> stations;
  final List<Dishes3> dishes;

  Map<String, Object?> toJson() => _$Template3ToJson(this);
}
