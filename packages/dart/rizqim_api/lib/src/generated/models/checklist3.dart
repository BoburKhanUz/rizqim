// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'done_by2.dart';

part 'checklist3.g.dart';

@JsonSerializable()
class Checklist3 {
  const Checklist3({
    required this.key,
    required this.label,
    required this.hint,
    required this.done,
    required this.doneAt,
    required this.doneBy,
  });

  factory Checklist3.fromJson(Map<String, Object?> json) =>
      _$Checklist3FromJson(json);

  final String key;
  final String label;
  final String hint;
  final bool done;
  @JsonKey(name: 'done_at')
  final String? doneAt;
  @JsonKey(name: 'done_by')
  final DoneBy2? doneBy;

  Map<String, Object?> toJson() => _$Checklist3ToJson(this);
}
