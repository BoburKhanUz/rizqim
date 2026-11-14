// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'rows12.dart';

part 'advances2.g.dart';

@JsonSerializable()
class Advances2 {
  const Advances2({
    required this.total,
    required this.applied,
    required this.rows,
  });

  factory Advances2.fromJson(Map<String, Object?> json) =>
      _$Advances2FromJson(json);

  final String total;
  final String applied;
  final List<Rows12> rows;

  Map<String, Object?> toJson() => _$Advances2ToJson(this);
}
