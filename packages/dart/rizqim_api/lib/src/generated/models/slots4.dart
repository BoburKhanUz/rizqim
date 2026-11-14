// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'morning.dart';
import 'day.dart';
import 'evening.dart';

part 'slots4.g.dart';

@JsonSerializable()
class Slots4 {
  const Slots4({
    required this.morning,
    required this.day,
    required this.evening,
  });

  factory Slots4.fromJson(Map<String, Object?> json) => _$Slots4FromJson(json);

  final Morning morning;
  final Day day;
  final Evening evening;

  Map<String, Object?> toJson() => _$Slots4ToJson(this);
}
