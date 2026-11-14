// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'rows14.dart';

part 'advances4.g.dart';

@JsonSerializable()
class Advances4 {
  const Advances4({
    required this.total,
    required this.applied,
    required this.rows,
  });

  factory Advances4.fromJson(Map<String, Object?> json) =>
      _$Advances4FromJson(json);

  final String total;
  final String applied;
  final List<Rows14> rows;

  Map<String, Object?> toJson() => _$Advances4ToJson(this);
}
