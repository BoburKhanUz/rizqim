// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'rows13.dart';

part 'advances3.g.dart';

@JsonSerializable()
class Advances3 {
  const Advances3({
    required this.total,
    required this.applied,
    required this.rows,
  });

  factory Advances3.fromJson(Map<String, Object?> json) =>
      _$Advances3FromJson(json);

  final String total;
  final String applied;
  final List<Rows13> rows;

  Map<String, Object?> toJson() => _$Advances3ToJson(this);
}
