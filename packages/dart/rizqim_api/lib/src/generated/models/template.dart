// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'stations4.dart';
import 'dishes.dart';

part 'template.g.dart';

@JsonSerializable()
class Template {
  const Template({
    required this.profile,
    required this.stations,
    required this.dishes,
  });

  factory Template.fromJson(Map<String, Object?> json) =>
      _$TemplateFromJson(json);

  final String profile;
  final List<Stations4> stations;
  final List<Dishes> dishes;

  Map<String, Object?> toJson() => _$TemplateToJson(this);
}
