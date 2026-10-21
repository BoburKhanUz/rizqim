// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'working_hours5.g.dart';

@JsonSerializable()
class WorkingHours5 {
  const WorkingHours5({this.open, this.close});

  factory WorkingHours5.fromJson(Map<String, Object?> json) =>
      _$WorkingHours5FromJson(json);

  final String? open;
  final String? close;

  Map<String, Object?> toJson() => _$WorkingHours5ToJson(this);
}
