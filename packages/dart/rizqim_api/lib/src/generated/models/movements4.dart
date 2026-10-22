// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'employee5.dart';
import 'user23.dart';
import 'approver5.dart';

part 'movements4.g.dart';

@JsonSerializable()
class Movements4 {
  const Movements4({
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

  factory Movements4.fromJson(Map<String, Object?> json) =>
      _$Movements4FromJson(json);

  final String uuid;
  final String type;
  final String? category;
  final String amount;
  final String? note;
  final Employee5? employee;
  final User23? user;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  final Approver5? approver;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Movements4ToJson(this);
}
