// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'employee.dart';
import 'user19.dart';
import 'approver.dart';

part 'movements.g.dart';

@JsonSerializable()
class Movements {
  const Movements({
    required this.uuid,
    required this.type,
    required this.category,
    required this.amount,
    required this.note,
    required this.employee,
    required this.user,
    required this.approvedBy,
    required this.createdAt,
    this.approver,
  });

  factory Movements.fromJson(Map<String, Object?> json) =>
      _$MovementsFromJson(json);

  final String uuid;
  final String type;
  final String? category;
  final String amount;
  final String? note;
  final Employee? employee;
  final User19? user;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  final Approver? approver;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$MovementsToJson(this);
}
