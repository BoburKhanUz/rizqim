// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kind3.dart';

part 'rows9.g.dart';

@JsonSerializable()
class Rows9 {
  const Rows9({
    required this.kind,
    required this.dueDate,
    required this.amount,
    this.uuid,
  });

  factory Rows9.fromJson(Map<String, Object?> json) => _$Rows9FromJson(json);

  final String? uuid;
  final Kind3 kind;
  @JsonKey(name: 'due_date')
  final DateTime dueDate;
  final num amount;

  Map<String, Object?> toJson() => _$Rows9ToJson(this);
}
