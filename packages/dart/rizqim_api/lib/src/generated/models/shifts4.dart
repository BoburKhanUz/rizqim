// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'shift3.dart';
import 'report2.dart';

part 'shifts4.g.dart';

@JsonSerializable()
class Shifts4 {
  const Shifts4({required this.shift, required this.report});

  factory Shifts4.fromJson(Map<String, Object?> json) =>
      _$Shifts4FromJson(json);

  final Shift3 shift;
  final Report2 report;

  Map<String, Object?> toJson() => _$Shifts4ToJson(this);
}
