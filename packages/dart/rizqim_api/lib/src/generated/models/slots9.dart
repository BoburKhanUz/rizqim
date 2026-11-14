// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'morning5.dart';
import 'day5.dart';
import 'evening5.dart';

part 'slots9.g.dart';

@JsonSerializable()
class Slots9 {
  const Slots9({
    required this.morning,
    required this.day,
    required this.evening,
  });

  factory Slots9.fromJson(Map<String, Object?> json) => _$Slots9FromJson(json);

  final Morning5 morning;
  final Day5 day;
  final Evening5 evening;

  Map<String, Object?> toJson() => _$Slots9ToJson(this);
}
