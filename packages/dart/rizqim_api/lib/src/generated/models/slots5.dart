// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'morning2.dart';
import 'day2.dart';
import 'evening2.dart';

part 'slots5.g.dart';

@JsonSerializable()
class Slots5 {
  const Slots5({
    required this.morning,
    required this.day,
    required this.evening,
  });

  factory Slots5.fromJson(Map<String, Object?> json) => _$Slots5FromJson(json);

  final Morning2 morning;
  final Day2 day;
  final Evening2 evening;

  Map<String, Object?> toJson() => _$Slots5ToJson(this);
}
