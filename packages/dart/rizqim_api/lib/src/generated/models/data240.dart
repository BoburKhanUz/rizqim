// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'schedule.dart';
import 'entry.dart';

part 'data240.g.dart';

@JsonSerializable()
class Data240 {
  const Data240({required this.schedule, required this.entry});

  factory Data240.fromJson(Map<String, Object?> json) =>
      _$Data240FromJson(json);

  final Schedule schedule;
  final Entry entry;

  Map<String, Object?> toJson() => _$Data240ToJson(this);
}
