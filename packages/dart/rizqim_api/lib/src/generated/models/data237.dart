// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'schedule.dart';
import 'entry.dart';

part 'data237.g.dart';

@JsonSerializable()
class Data237 {
  const Data237({required this.schedule, required this.entry});

  factory Data237.fromJson(Map<String, Object?> json) =>
      _$Data237FromJson(json);

  final Schedule schedule;
  final Entry entry;

  Map<String, Object?> toJson() => _$Data237ToJson(this);
}
