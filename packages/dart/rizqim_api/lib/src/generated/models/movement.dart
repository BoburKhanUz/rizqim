// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'movement.g.dart';

@JsonSerializable()
class Movement {
  const Movement({
    required this.uuid,
    required this.type,
    required this.category,
    required this.amount,
    required this.note,
    required this.employeeId,
    required this.approvedBy,
    required this.createdAt,
  });

  factory Movement.fromJson(Map<String, Object?> json) =>
      _$MovementFromJson(json);

  final String uuid;
  final String type;
  final String? category;
  final String amount;
  final String? note;
  @JsonKey(name: 'employee_id')
  final int? employeeId;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$MovementToJson(this);
}
