// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'stations5.dart';
import 'dishes2.dart';

part 'template2.g.dart';

@JsonSerializable()
class Template2 {
  const Template2({
    required this.profile,
    required this.stations,
    required this.dishes,
  });

  factory Template2.fromJson(Map<String, Object?> json) =>
      _$Template2FromJson(json);

  final String profile;
  final List<Stations5> stations;
  final List<Dishes2> dishes;

  Map<String, Object?> toJson() => _$Template2ToJson(this);
}
