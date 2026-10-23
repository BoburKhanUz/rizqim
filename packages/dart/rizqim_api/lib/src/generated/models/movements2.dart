// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'employee2.dart';
import 'user20.dart';
import 'approver2.dart';

part 'movements2.g.dart';

@JsonSerializable()
class Movements2 {
  const Movements2({
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

  factory Movements2.fromJson(Map<String, Object?> json) =>
      _$Movements2FromJson(json);

  final String uuid;
  final String type;
  final String? category;
  final String amount;
  final String? note;
  final Employee2? employee;
  final User20? user;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  final Approver2? approver;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Movements2ToJson(this);
}
