// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'recorded_by4.g.dart';

@JsonSerializable()
class RecordedBy4 {
  const RecordedBy4({required this.id, required this.name});

  factory RecordedBy4.fromJson(Map<String, Object?> json) =>
      _$RecordedBy4FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$RecordedBy4ToJson(this);
}
