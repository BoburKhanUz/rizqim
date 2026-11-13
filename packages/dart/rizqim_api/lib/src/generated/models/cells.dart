// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'cells.g.dart';

@JsonSerializable()
class Cells {
  const Cells({required this.date, required this.amount, required this.meals});

  factory Cells.fromJson(Map<String, Object?> json) => _$CellsFromJson(json);

  final String date;
  final String amount;
  final int meals;

  Map<String, Object?> toJson() => _$CellsToJson(this);
}
