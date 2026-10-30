// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'fire_mode.dart';
import 'combo2.dart';

part 'items22.g.dart';

@JsonSerializable()
class Items22 {
  const Items22({
    required this.variantId,
    required this.quantity,
    this.modifiers,
    this.note,
    this.seatNo,
    this.fireMode,
    this.dueAt,
    this.courseNo,
    this.awaitingWeight,
    this.combo,
  });

  factory Items22.fromJson(Map<String, Object?> json) =>
      _$Items22FromJson(json);

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

  /// P6-31: a combo's picks ({option_id, quantity?, modifiers?}; docs/MENU.md "Kombo")
  final List<Combo2>? combo;

  Map<String, Object?> toJson() => _$Items22ToJson(this);
}
