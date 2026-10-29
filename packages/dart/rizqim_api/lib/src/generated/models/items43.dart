// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'fire_mode.dart';

part 'items43.g.dart';

@JsonSerializable()
class Items43 {
  const Items43({
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

  factory Items43.fromJson(Map<String, Object?> json) =>
      _$Items43FromJson(json);

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
  @JsonKey(name: 'course_no')
  final int? courseNo;

  /// P6-21
  @JsonKey(name: 'awaiting_weight')
  final bool? awaitingWeight;

  Map<String, Object?> toJson() => _$Items43ToJson(this);
}
