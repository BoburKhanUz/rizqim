// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'lines26.g.dart';

@JsonSerializable()
class Lines26 {
  const Lines26({
    required this.name,
    required this.qty,
    required this.price,
    required this.total,
    this.unit,
    this.modifiers,
    this.note,
  });

  factory Lines26.fromJson(Map<String, Object?> json) =>
      _$Lines26FromJson(json);

  final String name;
  final String qty;
  final String? unit;
  final String price;
  final String total;
  final List<String>? modifiers;
  final String? note;

  Map<String, Object?> toJson() => _$Lines26ToJson(this);
}
