// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'member3.dart';
import 'cells.dart';

part 'rows15.g.dart';

@JsonSerializable()
class Rows15 {
  const Rows15({
    required this.member,
    required this.cells,
    required this.amount,
    required this.meals,
  });

  factory Rows15.fromJson(Map<String, Object?> json) => _$Rows15FromJson(json);

  final Member3? member;
  final List<Cells> cells;
  final String amount;
  final int meals;

  Map<String, Object?> toJson() => _$Rows15ToJson(this);
}
