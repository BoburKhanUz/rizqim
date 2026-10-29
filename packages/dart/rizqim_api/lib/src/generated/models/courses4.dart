// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'courses4.g.dart';

@JsonSerializable()
class Courses4 {
  const Courses4({
    required this.courseNo,
    required this.status,
    required this.items,
    required this.sent,
    required this.ready,
    required this.served,
  });

  factory Courses4.fromJson(Map<String, Object?> json) =>
      _$Courses4FromJson(json);

  @JsonKey(name: 'course_no')
  final int courseNo;
  final String status;
  final int items;
  final int sent;
  final int ready;
  final int served;

  Map<String, Object?> toJson() => _$Courses4ToJson(this);
}
