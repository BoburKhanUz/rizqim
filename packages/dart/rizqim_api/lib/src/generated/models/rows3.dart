// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'member3.dart';
import 'cells.dart';

part 'rows3.g.dart';

@JsonSerializable()
class Rows3 {
  const Rows3({
    required this.member,
    required this.cells,
    required this.amount,
    required this.meals,
  });

  factory Rows3.fromJson(Map<String, Object?> json) => _$Rows3FromJson(json);

  final Member3? member;
  final List<Cells> cells;
  final String amount;
  final int meals;

  Map<String, Object?> toJson() => _$Rows3ToJson(this);
}
