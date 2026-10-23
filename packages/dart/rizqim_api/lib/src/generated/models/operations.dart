// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'type9.dart';

part 'operations.g.dart';

@JsonSerializable()
class Operations {
  const Operations({
    required this.opId,
    required this.seq,
    required this.type,
    required this.staffId,
    required this.payload,
    this.occurredAt,
  });

  factory Operations.fromJson(Map<String, Object?> json) =>
      _$OperationsFromJson(json);

  @JsonKey(name: 'op_id')
  final String opId;
  final int seq;
  final Type9 type;
  @JsonKey(name: 'occurred_at')
  final DateTime? occurredAt;
  @JsonKey(name: 'staff_id')
  final int staffId;
  final List<String> payload;

  Map<String, Object?> toJson() => _$OperationsToJson(this);
}
