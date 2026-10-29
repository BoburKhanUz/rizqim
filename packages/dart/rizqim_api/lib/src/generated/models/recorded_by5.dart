// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'recorded_by5.g.dart';

@JsonSerializable()
class RecordedBy5 {
  const RecordedBy5({required this.id, required this.name});

  factory RecordedBy5.fromJson(Map<String, Object?> json) =>
      _$RecordedBy5FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$RecordedBy5ToJson(this);
}
