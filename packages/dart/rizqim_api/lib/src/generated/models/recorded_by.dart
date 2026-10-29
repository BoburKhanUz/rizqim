// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'recorded_by.g.dart';

@JsonSerializable()
class RecordedBy {
  const RecordedBy({required this.id, required this.name});

  factory RecordedBy.fromJson(Map<String, Object?> json) =>
      _$RecordedByFromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$RecordedByToJson(this);
}
