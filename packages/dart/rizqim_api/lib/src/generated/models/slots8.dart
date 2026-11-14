// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'morning4.dart';
import 'day4.dart';
import 'evening4.dart';

part 'slots8.g.dart';

@JsonSerializable()
class Slots8 {
  const Slots8({
    required this.morning,
    required this.day,
    required this.evening,
  });

  factory Slots8.fromJson(Map<String, Object?> json) => _$Slots8FromJson(json);

  final Morning4 morning;
  final Day4 day;
  final Evening4 evening;

  Map<String, Object?> toJson() => _$Slots8ToJson(this);
}
