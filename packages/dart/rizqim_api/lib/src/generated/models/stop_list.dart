// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'stop_list.g.dart';

@JsonSerializable()
class StopList {
  const StopList({
    required this.id,
    required this.type,
    required this.subjectId,
    required this.reason,
    required this.until,
  });

  factory StopList.fromJson(Map<String, Object?> json) =>
      _$StopListFromJson(json);

  final int? id;
  final String type;
  @JsonKey(name: 'subject_id')
  final int subjectId;
  final String? reason;
  final String? until;

  Map<String, Object?> toJson() => _$StopListToJson(this);
}
