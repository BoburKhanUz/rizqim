// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'lines15.g.dart';

@JsonSerializable()
class Lines15 {
  const Lines15({
    required this.name,
    required this.qty,
    required this.unit,
    required this.price,
    required this.total,
    required this.modifiers,
    required this.note,
  });

  factory Lines15.fromJson(Map<String, Object?> json) =>
      _$Lines15FromJson(json);

  final String name;
  final String qty;
  final String? unit;
  final String price;
  final String total;
  final List<String> modifiers;
  final String? note;

  Map<String, Object?> toJson() => _$Lines15ToJson(this);
}
