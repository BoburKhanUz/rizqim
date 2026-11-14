// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'schedule2.dart';
import 'entry2.dart';
import 'fiscal20.dart';

part 'data235.g.dart';

@JsonSerializable()
class Data235 {
  const Data235({
    required this.schedule,
    required this.entry,
    required this.fiscal,
  });

  factory Data235.fromJson(Map<String, Object?> json) =>
      _$Data235FromJson(json);

  final Schedule2 schedule;
  final Entry2 entry;
  final Fiscal20 fiscal;

  Map<String, Object?> toJson() => _$Data235ToJson(this);
}
