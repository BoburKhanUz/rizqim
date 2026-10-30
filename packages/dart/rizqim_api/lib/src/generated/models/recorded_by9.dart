// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'recorded_by9.g.dart';

@JsonSerializable()
class RecordedBy9 {
  const RecordedBy9({required this.id, required this.name});

  factory RecordedBy9.fromJson(Map<String, Object?> json) =>
      _$RecordedBy9FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$RecordedBy9ToJson(this);
}
