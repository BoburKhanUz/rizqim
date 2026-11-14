// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'morning3.dart';
import 'day3.dart';
import 'evening3.dart';

part 'slots7.g.dart';

@JsonSerializable()
class Slots7 {
  const Slots7({
    required this.morning,
    required this.day,
    required this.evening,
  });

  factory Slots7.fromJson(Map<String, Object?> json) => _$Slots7FromJson(json);

  final Morning3 morning;
  final Day3 day;
  final Evening3 evening;

  Map<String, Object?> toJson() => _$Slots7ToJson(this);
}
