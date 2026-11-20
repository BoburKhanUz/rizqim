// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'schedule2.dart';
import 'entry2.dart';
import 'fiscal22.dart';

part 'data238.g.dart';

@JsonSerializable()
class Data238 {
  const Data238({
    required this.schedule,
    required this.entry,
    required this.fiscal,
  });

  factory Data238.fromJson(Map<String, Object?> json) =>
      _$Data238FromJson(json);

  final Schedule2 schedule;
  final Entry2 entry;
  final Fiscal22 fiscal;

  Map<String, Object?> toJson() => _$Data238ToJson(this);
}
