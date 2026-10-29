// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'lines14.g.dart';

@JsonSerializable()
class Lines14 {
  const Lines14({
    required this.name,
    required this.qty,
    required this.price,
    required this.total,
    this.unit,
    this.modifiers,
    this.note,
  });

  factory Lines14.fromJson(Map<String, Object?> json) =>
      _$Lines14FromJson(json);

  final String name;
  final String qty;
  final String? unit;
  final String price;
  final String total;
  final List<String>? modifiers;
  final String? note;

  Map<String, Object?> toJson() => _$Lines14ToJson(this);
}
