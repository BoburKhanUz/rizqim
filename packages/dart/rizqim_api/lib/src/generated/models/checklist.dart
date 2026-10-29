// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'done_by.dart';

part 'checklist.g.dart';

@JsonSerializable()
class Checklist {
  const Checklist({
    required this.key,
    required this.label,
    required this.hint,
    required this.done,
    required this.doneAt,
    required this.doneBy,
  });

  factory Checklist.fromJson(Map<String, Object?> json) =>
      _$ChecklistFromJson(json);

  final String key;
  final String label;
  final String hint;
  final bool done;
  @JsonKey(name: 'done_at')
  final String? doneAt;
  @JsonKey(name: 'done_by')
  final DoneBy? doneBy;

  Map<String, Object?> toJson() => _$ChecklistToJson(this);
}
