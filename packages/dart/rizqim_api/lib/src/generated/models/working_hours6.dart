// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'working_hours6.g.dart';

@JsonSerializable()
class WorkingHours6 {
  const WorkingHours6({this.open, this.close});

  factory WorkingHours6.fromJson(Map<String, Object?> json) =>
      _$WorkingHours6FromJson(json);

  final String? open;
  final String? close;

  Map<String, Object?> toJson() => _$WorkingHours6ToJson(this);
}
