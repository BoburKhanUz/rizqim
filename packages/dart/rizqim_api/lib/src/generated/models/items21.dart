// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'fire_mode.dart';

part 'items21.g.dart';

@JsonSerializable()
class Items21 {
  const Items21({
    required this.variantId,
    required this.quantity,
    this.modifiers,
    this.note,
    this.seatNo,
    this.fireMode,
    this.dueAt,
    this.courseNo,
    this.awaitingWeight,
  });

  factory Items21.fromJson(Map<String, Object?> json) =>
      _$Items21FromJson(json);

  @JsonKey(name: 'variant_id')
  final String variantId;
  final num quantity;
  final List<String>? modifiers;
  final String? note;
  @JsonKey(name: 'seat_no')
  final int? seatNo;
  @JsonKey(name: 'fire_mode')
  final FireMode? fireMode;
  @JsonKey(name: 'due_at')
  final DateTime? dueAt;

  /// P6-21: the line's course (module courses); P6-25: a dish weighed later (module weight_later)
  @JsonKey(name: 'course_no')
  final int? courseNo;
  @JsonKey(name: 'awaiting_weight')
  final bool? awaitingWeight;

  Map<String, Object?> toJson() => _$Items21ToJson(this);
}
