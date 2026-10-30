// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'recorded_by7.g.dart';

@JsonSerializable()
class RecordedBy7 {
  const RecordedBy7({required this.id, required this.name});

  factory RecordedBy7.fromJson(Map<String, Object?> json) =>
      _$RecordedBy7FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$RecordedBy7ToJson(this);
}
